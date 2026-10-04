<?php
/**
 * MongoDB SIMULADO para pruebas automáticas (NO se usa en producción).
 *
 * Reemplaza las clases de la extensión mongodb y de la librería mongodb/mongodb con una
 * implementación en memoria persistida en un archivo, suficiente para ejecutar las páginas
 * de CERAMISHOP sin un servidor MongoDB real. Se carga con:
 *   php -d auto_prepend_file=tests/fake_mongo.php ...
 * Archivo de datos: variable de entorno CS_FAKE_DB (por defecto sys_get_temp_dir()/cs_fake_db.ser)
 */

namespace MongoDB\BSON {
    final class ObjectId implements \JsonSerializable
    {
        private string $oid;
        public function __construct(?string $id = null)
        {
            if ($id === null) {
                static $c = 0;
                $id = sprintf('%08x', time()) . bin2hex(random_bytes(5)) . sprintf('%06x', (++$c + random_int(0, 0xfff)) & 0xffffff);
            }
            if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
                throw new \MongoDB\Driver\Exception\InvalidArgumentException("Error parsing ObjectId string: $id");
            }
            $this->oid = strtolower($id);
        }
        public function __toString(): string { return $this->oid; }
        public function getTimestamp(): int { return hexdec(substr($this->oid, 0, 8)); }
        public function jsonSerialize(): mixed { return ['$oid' => $this->oid]; }
    }

    final class UTCDateTime implements \JsonSerializable
    {
        private int $ms;
        public function __construct($ms = null)
        {
            if ($ms instanceof \DateTimeInterface) {
                $ms = (int)$ms->format('Uv');
            }
            $this->ms = $ms === null ? (int)floor(microtime(true) * 1000) : (int)$ms;
        }
        public function toDateTime(): \DateTime
        {
            $dt = \DateTime::createFromFormat('U.u', sprintf('%d.%03d000', intdiv($this->ms, 1000), $this->ms % 1000));
            return $dt->setTimezone(new \DateTimeZone('UTC'));
        }
        public function __toString(): string { return (string)$this->ms; }
        public function jsonSerialize(): mixed { return ['$date' => $this->ms]; }
    }

    final class Regex
    {
        public function __construct(public string $pattern, public string $flags = '') {}
        public function getPattern(): string { return $this->pattern; }
        public function getFlags(): string { return $this->flags; }
    }
}

namespace MongoDB\Driver\Exception {
    class Exception extends \Exception {}
    class RuntimeException extends Exception {}
    class InvalidArgumentException extends Exception {}
    class ConnectionException extends RuntimeException {}
    class ConnectionTimeoutException extends ConnectionException {}
    class BulkWriteException extends RuntimeException {}
}

namespace MongoDB\Operation {
    final class FindOneAndUpdate
    {
        public const RETURN_DOCUMENT_BEFORE = 1;
        public const RETURN_DOCUMENT_AFTER = 2;
    }
}

namespace MongoDB {

    use MongoDB\BSON\ObjectId;
    use MongoDB\BSON\UTCDateTime;
    use MongoDB\BSON\Regex;

    final class FakeStore
    {
        public static ?array $data = null;
        public static function archivo(): string
        {
            return getenv('CS_FAKE_DB') ?: sys_get_temp_dir() . '/cs_fake_db.ser';
        }
        public static function cargar(): void
        {
            if (getenv('CS_FAKE_MONGO_CAIDO') === '1') {
                throw new \MongoDB\Driver\Exception\ConnectionTimeoutException('No suitable servers found (simulado)');
            }
            if (self::$data === null) {
                $f = self::archivo();
                self::$data = is_file($f) ? (unserialize((string)file_get_contents($f)) ?: []) : [];
            }
        }
        public static function guardar(): void
        {
            file_put_contents(self::archivo(), serialize(self::$data), LOCK_EX);
        }
    }

    final class Cursor extends \ArrayIterator
    {
        public function toArray(): array { return $this->getArrayCopy(); }
    }

    final class InsertOneResult
    {
        public function __construct(private $id) {}
        public function getInsertedId() { return $this->id; }
        public function getInsertedCount(): int { return 1; }
    }
    final class InsertManyResult
    {
        public function __construct(private array $ids) {}
        public function getInsertedIds(): array { return $this->ids; }
        public function getInsertedCount(): int { return count($this->ids); }
    }
    final class UpdateResult
    {
        public function __construct(private int $matched, private int $modified, private $upserted = null) {}
        public function getMatchedCount(): int { return $this->matched; }
        public function getModifiedCount(): int { return $this->modified; }
        public function getUpsertedId() { return $this->upserted; }
    }
    final class DeleteResult
    {
        public function __construct(private int $n) {}
        public function getDeletedCount(): int { return $this->n; }
    }

    class Client
    {
        public function __construct(string $uri = 'mongodb://127.0.0.1/', array $uriOptions = [], array $driverOptions = []) {}
        public function selectDatabase(string $name, array $options = []): Database { return new Database($name); }
        public function __get($name) { return $this->selectDatabase($name); }
    }

    class Database
    {
        public function __construct(private string $name) {}
        public function selectCollection(string $name, array $options = []): Collection { return new Collection($this->name, $name); }
        public function __get($name) { return $this->selectCollection($name); }
        public function command($cmd, array $options = []): Cursor
        {
            FakeStore::cargar();
            return new Cursor([['ok' => 1]]);
        }
        public function getDatabaseName(): string { return $this->name; }
    }

    class Collection
    {
        private string $key;
        public function __construct(private string $db, private string $name)
        {
            $this->key = $db . '.' . $name;
        }

        private function &docs(): array
        {
            FakeStore::cargar();
            if (!isset(FakeStore::$data[$this->key])) {
                FakeStore::$data[$this->key] = [];
            }
            return FakeStore::$data[$this->key];
        }

        /* ------------------ valores y comparaciones ------------------ */
        public static function norm($v)
        {
            if ($v instanceof ObjectId) return (string)$v;
            if ($v instanceof UTCDateTime) return (int)(string)$v;
            return $v;
        }
        public static function cmp($a, $b): int
        {
            $a = self::norm($a);
            $b = self::norm($b);
            if ($a === null && $b === null) return 0;
            if ($a === null) return -1;
            if ($b === null) return 1;
            if (is_numeric($a) && is_numeric($b)) return $a <=> $b;
            return strcmp((string)$a, (string)$b);
        }
        public static function valores(array $doc, string $ruta): array
        {
            $partes = explode('.', $ruta);
            $actuales = [$doc];
            foreach ($partes as $p) {
                $sig = [];
                foreach ($actuales as $a) {
                    if (is_array($a) && array_key_exists($p, $a)) {
                        $sig[] = $a[$p];
                    } elseif (is_array($a) && array_is_list($a)) {
                        foreach ($a as $el) {
                            if (is_array($el) && array_key_exists($p, $el)) $sig[] = $el[$p];
                        }
                    }
                }
                $actuales = $sig;
            }
            return $actuales;
        }
        public static function obtener(array $doc, string $ruta)
        {
            $v = self::valores($doc, $ruta);
            return $v ? $v[0] : null;
        }
        private static function igual($v, $cond, bool $ci): bool
        {
            if ($cond instanceof Regex) {
                return is_string($v) && preg_match('/' . str_replace('/', '\/', $cond->pattern) . '/' . str_replace(['s', 'x'], ['s', 'x'], $cond->flags) . 'u', $v) === 1;
            }
            if (is_array($v) && array_is_list($v) && !is_array($cond)) {
                foreach ($v as $el) {
                    if (self::igual($el, $cond, $ci)) return true;
                }
                return false;
            }
            $a = self::norm($v);
            $b = self::norm($cond);
            if ($ci && is_string($a) && is_string($b)) return mb_strtolower($a) === mb_strtolower($b);
            if (is_numeric($a) && is_numeric($b) && !is_string($a) && !is_string($b)) return $a == $b;
            return $a === $b;
        }
        private static function esOperadores($c): bool
        {
            if (!is_array($c) || !$c) return false;
            foreach (array_keys($c) as $k) {
                if (!is_string($k) || $k[0] !== '$') return false;
            }
            return true;
        }
        public static function cumple(array $doc, array $filtro, bool $ci = false): bool
        {
            foreach ($filtro as $campo => $cond) {
                if ($campo === '$or') {
                    $ok = false;
                    foreach ($cond as $sub) {
                        if (self::cumple($doc, $sub, $ci)) { $ok = true; break; }
                    }
                    if (!$ok) return false;
                    continue;
                }
                if ($campo === '$and') {
                    foreach ($cond as $sub) {
                        if (!self::cumple($doc, $sub, $ci)) return false;
                    }
                    continue;
                }
                if ($campo === '$nor') {
                    foreach ($cond as $sub) {
                        if (self::cumple($doc, $sub, $ci)) return false;
                    }
                    continue;
                }
                if ($campo === '$expr') {
                    if (!self::evaluar($cond, $doc)) return false;
                    continue;
                }
                $vals = self::valores($doc, $campo);
                $existe = count($vals) > 0;
                $v = $existe ? $vals[0] : null;
                if (self::esOperadores($cond)) {
                    $opciones = $cond['$options'] ?? '';
                    foreach ($cond as $op => $arg) {
                        switch ($op) {
                            case '$eq': if (!self::igual($v, $arg, $ci)) return false; break;
                            case '$ne': if (self::igual($v, $arg, $ci)) return false; break;
                            case '$gt': if (!$existe || self::cmp($v, $arg) <= 0) return false; break;
                            case '$gte': if (!$existe || self::cmp($v, $arg) < 0) return false; break;
                            case '$lt': if (!$existe || self::cmp($v, $arg) >= 0) return false; break;
                            case '$lte': if (!$existe || self::cmp($v, $arg) > 0) return false; break;
                            case '$in':
                                $ok = false;
                                foreach ($arg as $x) {
                                    if (self::igual($v, $x, $ci)) { $ok = true; break; }
                                }
                                if (!$ok) return false;
                                break;
                            case '$nin':
                                foreach ($arg as $x) {
                                    if (self::igual($v, $x, $ci)) return false;
                                }
                                break;
                            case '$exists': if ((bool)$arg !== $existe) return false; break;
                            case '$regex':
                                $patron = $arg instanceof Regex ? $arg->pattern : (string)$arg;
                                $re = '/' . str_replace('/', '\/', $patron) . '/u' . (strpos($opciones, 'i') !== false ? 'i' : '');
                                $vs = is_array($v) ? $v : [$v];
                                $ok = false;
                                foreach ($vs as $x) {
                                    if (is_string($x) && preg_match($re, $x)) { $ok = true; break; }
                                }
                                if (!$ok) return false;
                                break;
                            case '$options': break;
                            default: throw new \RuntimeException("Operador no soportado en fake: $op");
                        }
                    }
                } else {
                    if (!$existe && $cond === null) continue;
                    if (!self::igual($v, $cond, $ci)) return false;
                }
            }
            return true;
        }

        /** Evaluador mínimo de expresiones de agregación. */
        public static function evaluar($e, array $doc)
        {
            if (is_string($e) && $e !== '' && $e[0] === '$') {
                return self::obtener($doc, substr($e, 1));
            }
            if (!is_array($e) || array_is_list($e)) {
                return is_array($e) ? array_map(fn($x) => self::evaluar($x, $doc), $e) : $e;
            }
            $op = array_key_first($e);
            if (!is_string($op) || $op[0] !== '$') {
                $out = [];
                foreach ($e as $k => $sub) $out[$k] = self::evaluar($sub, $doc);
                return $out;
            }
            $arg = $e[$op];
            switch ($op) {
                case '$month':
                case '$year':
                    $tz = 'UTC';
                    $d = $arg;
                    if (is_array($arg) && isset($arg['date'])) { $d = $arg['date']; $tz = $arg['timezone'] ?? 'UTC'; }
                    $v = self::evaluar($d, $doc);
                    if (!$v instanceof UTCDateTime) return null;
                    $dt = $v->toDateTime()->setTimezone(new \DateTimeZone($tz));
                    return (int)$dt->format($op === '$month' ? 'n' : 'Y');
                case '$multiply':
                    $r = 1;
                    foreach ($arg as $x) $r *= (float)self::evaluar($x, $doc);
                    return $r;
                case '$toLower': return mb_strtolower((string)self::evaluar($arg, $doc));
                case '$toString': return (string)self::norm(self::evaluar($arg, $doc));
                case '$regexMatch':
                    $in = (string)self::evaluar($arg['input'], $doc);
                    return preg_match('/' . str_replace('/', '\/', $arg['regex']) . '/' . ($arg['options'] ?? ''), $in) === 1;
                case '$eq': return self::cmp(self::evaluar($arg[0], $doc), self::evaluar($arg[1], $doc)) === 0;
                default: throw new \RuntimeException("Expresión no soportada en fake: $op");
            }
        }

        private static function proyectar(array $doc, ?array $proj): array
        {
            if (!$proj) return $doc;
            $incluye = array_filter($proj, fn($v, $k) => $k !== '_id' && $v, ARRAY_FILTER_USE_BOTH);
            if ($incluye) {
                $out = [];
                if (($proj['_id'] ?? 1) && array_key_exists('_id', $doc)) $out['_id'] = $doc['_id'];
                foreach ($incluye as $k => $_) {
                    if (array_key_exists($k, $doc)) $out[$k] = $doc[$k];
                }
                return $out;
            }
            foreach ($proj as $k => $v) {
                if (!$v) unset($doc[$k]);
            }
            return $doc;
        }

        private static function ordenar(array &$docs, array $sort): void
        {
            usort($docs, function ($a, $b) use ($sort) {
                foreach ($sort as $campo => $dir) {
                    $va = self::obtener($a, $campo);
                    $vb = self::obtener($b, $campo);
                    if (is_string(self::norm($va)) && is_string(self::norm($vb)) && $campo !== '_id') {
                        $c = strcmp(mb_strtolower((string)$va), mb_strtolower((string)$vb));
                    } else {
                        $c = self::cmp($va, $vb);
                    }
                    if ($c !== 0) return $dir < 0 ? -$c : $c;
                }
                return 0;
            });
        }

        private function seleccionar(array $filtro, array $opc = []): array
        {
            $ci = isset($opc['collation']['strength']) && $opc['collation']['strength'] <= 2;
            $res = [];
            foreach ($this->docs() as $i => $d) {
                if (self::cumple($d, $filtro, $ci)) $res[$i] = $d;
            }
            return $res;
        }

        /* ------------------ API pública ------------------ */
        public function find($filtro = [], array $opc = []): Cursor
        {
            $docs = array_values($this->seleccionar((array)$filtro, $opc));
            if (!empty($opc['sort'])) self::ordenar($docs, $opc['sort']);
            if (!empty($opc['skip'])) $docs = array_slice($docs, (int)$opc['skip']);
            if (!empty($opc['limit'])) $docs = array_slice($docs, 0, (int)$opc['limit']);
            return new Cursor(array_map(fn($d) => self::proyectar($d, $opc['projection'] ?? null), $docs));
        }
        public function findOne($filtro = [], array $opc = [])
        {
            $opc['limit'] = 1;
            $r = $this->find($filtro, $opc)->toArray();
            return $r[0] ?? null;
        }
        public function countDocuments($filtro = [], array $opc = []): int
        {
            $n = count($this->seleccionar((array)$filtro, $opc));
            return !empty($opc['limit']) ? min($n, (int)$opc['limit']) : $n;
        }
        public function distinct(string $campo, $filtro = [], array $opc = []): array
        {
            $out = [];
            foreach ($this->seleccionar((array)$filtro) as $d) {
                foreach (self::valores($d, $campo) as $v) {
                    $out[serialize(self::norm($v))] = $v;
                }
            }
            return array_values($out);
        }
        public function insertOne($doc, array $opc = []): InsertOneResult
        {
            $doc = (array)$doc;
            if (!isset($doc['_id'])) $doc = ['_id' => new ObjectId()] + $doc;
            $docs = &$this->docs();
            foreach ($docs as $d) {
                if (self::cmp($d['_id'], $doc['_id']) === 0) throw new \MongoDB\Driver\Exception\BulkWriteException('E11000 duplicate key');
            }
            $docs[] = $doc;
            FakeStore::guardar();
            return new InsertOneResult($doc['_id']);
        }
        public function insertMany(array $docs, array $opc = []): InsertManyResult
        {
            $ids = [];
            foreach ($docs as $d) $ids[] = $this->insertOne($d)->getInsertedId();
            return new InsertManyResult($ids);
        }
        private static function aplicar(array $doc, array $upd, bool $insert = false): array
        {
            foreach ($upd as $op => $campos) {
                foreach ((array)$campos as $campo => $valor) {
                    $ref = &$doc;
                    $partes = explode('.', $campo);
                    $ultimo = array_pop($partes);
                    foreach ($partes as $p) {
                        if (!isset($ref[$p]) || !is_array($ref[$p])) $ref[$p] = [];
                        $ref = &$ref[$p];
                    }
                    switch ($op) {
                        case '$set': $ref[$ultimo] = $valor; break;
                        case '$setOnInsert': if ($insert) $ref[$ultimo] = $valor; break;
                        case '$unset': unset($ref[$ultimo]); break;
                        case '$inc': $ref[$ultimo] = ($ref[$ultimo] ?? 0) + $valor; break;
                        case '$push': $ref[$ultimo] = array_merge(is_array($ref[$ultimo] ?? null) ? $ref[$ultimo] : [], [$valor]); break;
                        default: throw new \RuntimeException("Update no soportado en fake: $op");
                    }
                    unset($ref);
                }
            }
            return $doc;
        }
        public function updateOne($filtro, $upd, array $opc = []): UpdateResult
        {
            return $this->actualizar((array)$filtro, (array)$upd, $opc, false);
        }
        public function updateMany($filtro, $upd, array $opc = []): UpdateResult
        {
            return $this->actualizar((array)$filtro, (array)$upd, $opc, true);
        }
        private function actualizar(array $filtro, array $upd, array $opc, bool $muchos): UpdateResult
        {
            $docs = &$this->docs();
            $sel = $this->seleccionar($filtro, $opc);
            $mod = 0;
            foreach ($sel as $i => $d) {
                $nuevo = self::aplicar($d, $upd);
                if (serialize($nuevo) !== serialize($d)) $mod++;
                $docs[$i] = $nuevo;
                if (!$muchos) break;
            }
            $upsertId = null;
            if (!$sel && !empty($opc['upsert'])) {
                $base = [];
                foreach ($filtro as $k => $v) {
                    if ($k[0] !== '$' && !is_array($v)) $base[$k] = $v;
                }
                $nuevo = self::aplicar($base, $upd, true);
                $upsertId = $this->insertOne($nuevo)->getInsertedId();
                return new UpdateResult(0, 0, $upsertId);
            }
            FakeStore::guardar();
            return new UpdateResult(count($sel) ? ($muchos ? count($sel) : 1) : 0, $mod);
        }
        public function findOneAndUpdate($filtro, $upd, array $opc = [])
        {
            $antes = $this->findOne($filtro);
            $this->updateOne($filtro, $upd, $opc);
            if (($opc['returnDocument'] ?? 1) === 2) {
                return $antes ? $this->findOne(['_id' => $antes['_id']]) : $this->findOne($filtro);
            }
            return $antes;
        }
        public function deleteOne($filtro, array $opc = []): DeleteResult
        {
            $docs = &$this->docs();
            foreach ($this->seleccionar((array)$filtro) as $i => $_) {
                unset($docs[$i]);
                $docs = array_values($docs);
                FakeStore::guardar();
                return new DeleteResult(1);
            }
            return new DeleteResult(0);
        }
        public function deleteMany($filtro, array $opc = []): DeleteResult
        {
            $docs = &$this->docs();
            $sel = $this->seleccionar((array)$filtro);
            foreach ($sel as $i => $_) unset($docs[$i]);
            $docs = array_values($docs);
            FakeStore::guardar();
            return new DeleteResult(count($sel));
        }
        public function createIndex($claves, array $opc = []): string
        {
            return $opc['name'] ?? implode('_', array_map(fn($k, $v) => "{$k}_{$v}", array_keys($claves), $claves));
        }
        public function aggregate(array $pipeline, array $opc = []): Cursor
        {
            $docs = array_values($this->docs());
            foreach ($pipeline as $etapa) {
                $op = array_key_first($etapa);
                $arg = $etapa[$op];
                switch ($op) {
                    case '$match':
                        // Igual que el MongoDB real: un arreglo PHP vacío se envía como lista BSON y $match lo rechaza
                        if ($arg === []) {
                            throw new \MongoDB\Driver\Exception\RuntimeException('the match filter must be an expression in an object');
                        }
                        $arg = (array)$arg; // un objeto (stdClass) vacío sí es válido: coincide con todo
                        $docs = array_values(array_filter($docs, fn($d) => self::cumple($d, $arg)));
                        break;
                    case '$sort': self::ordenar($docs, $arg); break;
                    case '$limit': $docs = array_slice($docs, 0, (int)$arg); break;
                    case '$skip': $docs = array_slice($docs, (int)$arg); break;
                    case '$unwind':
                        $campo = substr(is_array($arg) ? $arg['path'] : $arg, 1);
                        $out = [];
                        foreach ($docs as $d) {
                            foreach ((array)($d[$campo] ?? []) as $el) {
                                $d2 = $d;
                                $d2[$campo] = $el;
                                $out[] = $d2;
                            }
                        }
                        $docs = $out;
                        break;
                    case '$lookup':
                        $otra = new Collection($this->db, $arg['from']);
                        foreach ($docs as &$d) {
                            $v = self::obtener($d, $arg['localField']);
                            $d[$arg['as']] = $otra->find([$arg['foreignField'] => $v])->toArray();
                        }
                        unset($d);
                        break;
                    case '$group':
                        $grupos = [];
                        foreach ($docs as $d) {
                            $id = self::evaluar($arg['_id'], $d);
                            $k = serialize(is_array($id) ? array_map([self::class, 'norm'], $id) : self::norm($id));
                            if (!isset($grupos[$k])) $grupos[$k] = ['_id' => $id, '__docs' => []];
                            $grupos[$k]['__docs'][] = $d;
                        }
                        $docs = [];
                        foreach ($grupos as $g) {
                            $r = ['_id' => $g['_id']];
                            foreach ($arg as $campo => $acc) {
                                if ($campo === '_id') continue;
                                $accOp = array_key_first($acc);
                                $expr = $acc[$accOp];
                                $vals = array_map(fn($d) => self::evaluar($expr, $d), $g['__docs']);
                                switch ($accOp) {
                                    case '$sum': $r[$campo] = array_sum(array_map(fn($v) => is_numeric($v) ? $v + 0 : 0, $vals)); break;
                                    case '$avg': $r[$campo] = $vals ? array_sum($vals) / count($vals) : null; break;
                                    case '$max': $r[$campo] = $vals ? max($vals) : null; break;
                                    case '$min': $r[$campo] = $vals ? min($vals) : null; break;
                                    case '$first': $r[$campo] = $vals[0] ?? null; break;
                                    case '$addToSet':
                                        $s = [];
                                        foreach ($vals as $v) $s[serialize(self::norm($v))] = $v;
                                        $r[$campo] = array_values($s);
                                        break;
                                    default: throw new \RuntimeException("Acumulador no soportado en fake: $accOp");
                                }
                            }
                            $docs[] = $r;
                        }
                        break;
                    default: throw new \RuntimeException("Etapa no soportada en fake: $op");
                }
            }
            return new Cursor($docs);
        }
        public function getCollectionName(): string { return $this->name; }
    }
}
