<?php
namespace OCA\Vapor\Db;
use OCA\Vapor\Tools\Helper as ToolsHelper;

class Helper
{
    //@var OC\DB\ConnectionAdapter
    private $conn;
    private $table = "vapor_info";

    // NOTE: do NOT store a QueryBuilder instance as a class property.
    // Nextcloud's QueryBuilder is stateful: once you call ->select() or
    // ->update(), it holds that state until ->reset() or until a new
    // builder is fetched. Sharing one builder across multiple queries in
    // the same request causes the second query to inherit the first
    // query's state and crash (e.g. building a SELECT when the builder
    // was last configured for an UPDATE). Fetch a fresh builder for each
    // query instead.
    public function __construct()
    {
        // BEGIN STEVE EDITS
        $this->conn = \OC::$server->get(\OCP\IDBConnection::class);
        // END STEVE EDITS
    }

    public function insert($insert)
    {
        $inserted = (bool) $this->conn->insertIfNotExist('*PREFIX*' . $this->table, $insert, [
            'gid',
        ]);
        return $inserted;
    }

    public function getAll()
    {
        $queryBuilder = $this->conn->getQueryBuilder()
            ->select('filename', 'type', 'gid', 'timestamp', 'status')
            ->from($this->table)
            ->executeQuery();
        return $queryBuilder->fetchAll();
    }

    public function getByUid($uid)
    {
        $queryBuilder = $this->conn->getQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('uid = :uid')
            ->setParameter('uid', $uid)
            ->executeQuery();
        return $queryBuilder->fetchAll();
    }

    public function getUidByGid($gid)
    {
        $queryBuilder = $this->conn->getQueryBuilder()
            ->select('uid')
            ->from($this->table)
            ->where('gid = :gid')
            ->setParameter('gid', $gid)
            ->executeQuery();
        return $queryBuilder->fetchOne();
    }

    public function getYtdlByUid($uid)
    {
        $qb = $this->conn->getQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('uid = :uid')
            ->andWhere('type = :type')
            ->setParameter('uid', $uid)
            ->setParameter('type', ToolsHelper::DOWNLOADTYPE['YOUTUBE-DL'])
            ->orderBy('id', 'DESC')
            ->executeQuery();
        return $qb->fetchAll();
    }

    public function getByGid($gid)
    {
        $queryBuilder = $this->conn->getQueryBuilder()
            ->select('*')
            ->from($this->table)
            ->where('gid = :gid')
            ->setParameter('gid', $gid)
            ->executeQuery();
        return $queryBuilder->fetchAssociative();
    }

    public function save(array $keys, $values = array(), $conditions = array())
    {
        return $this->conn->setValues($this->table, $keys, $values, $conditions);
    }

    public function deleteByGid($gid)
    {
        $qb = $this->conn->getQueryBuilder()
            ->delete($this->table)
            ->where('gid = :gid')
            ->setParameter('gid', $gid);
        return $qb->executeStatement();
    }

    public function executeUpdate($sql, $values)
    {
        return $this->conn->executeUpdate($sql, $values);
    }

    public function updateStatus($gid, $status = 1)
    {
        $query = $this->conn->getQueryBuilder();
        $query->update($this->table)
            ->set("status", $query->createNamedParameter($status))
            ->where('gid = :gid')
            ->setParameter('gid', $gid);
        return $query->executeStatement();
    }

    public function updateFilename($gid, $filename)
    {
        $query = $this->conn->getQueryBuilder();
        $query->update($this->table)
            ->set("filename", $query->createNamedParameter($filename))
            ->where('gid = :gid')
            ->andWhere('filename = :filename')
            ->setParameter('gid', $gid)
            ->setParameter('filename', 'unknown');
        return $query->executeStatement();
    }

    /**
     * Merge arbitrary keys into the serialized `data` column for a gid,
     * preserving any existing keys (link, ext, path). Used by the async
     * ytdl flow to persist the child PID and its log file path so the
     * watcher can find them later.
     *
     * @param string $gid
     * @param array  $merge  keys to add/overwrite in the data column
     * @return bool  true on success, false if no row exists for $gid
     */
    public function mergeData(string $gid, array $merge): bool
    {
        $row = $this->getByGid($gid);
        if (!$row) {
            return false;
        }
        $current = [];
        if (!empty($row['data'])) {
            $decoded = $this->getExtra($row['data']);
            if (is_array($decoded)) {
                $current = $decoded;
            }
        }
        $merged = array_merge($current, $merge);

        $qb = $this->conn->getQueryBuilder();
        $qb->update($this->table)
            ->set('data', $qb->createNamedParameter(serialize($merged)))
            ->where('gid = :gid')
            ->setParameter('gid', $gid);
        return (bool) $qb->executeStatement();
    }

    public function getDBType(): string
    {
        // BEGIN STEVE EDITS
        return \OC::$server->get(\OCP\IConfig::class)->getSystemValue('dbtype', "mysql");
        // END STEVE EDITS
    }

    public function getExtra($data)
    {
        if ($this->getDBType() == "pgsql" && is_resource($data)) {
            if (function_exists("pg_unescape_bytea")) {
                $extra = pg_unescape_bytea(stream_get_contents($data));
            } else {
                $extra = stream_get_contents($data);
            }
            return unserialize($extra);
        }
        return unserialize($data);
    }

    /**
     * Prefixed table name for the vapor_info table, e.g. "oc_vapor_info".
     * Replaces the old public $queryBuilder property that callers used
     * to reach into; that property has been removed because sharing a
     * QueryBuilder across multiple queries caused state-leak bugs.
     */
    public function getPrefixedTable(): string
    {
        return $this->conn->getQueryBuilder()->getTableName($this->table);
    }



}
