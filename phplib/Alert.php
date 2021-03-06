<?php

namespace FOO;

/**
 * Alert Class
 * An alert is a result generated by a Search.
 */
class Alert extends Model {
    public static $TABLE = 'alerts';
    public static $PKEY = 'alert_id';

    // Alert states.
    /** New state */
    const ST_NEW = 0;
    /** In progress state */
    const ST_INPROG = 1;
    /** Resolved state */
    const ST_RES = 2;
    /** @var string[] Mapping of states to a user-friendly string. */
    public static $STATES = [
        self::ST_NEW => 'New',
        self::ST_INPROG => 'In Progress',
        self::ST_RES => 'Resolved',
    ];

    // Alert resolutions.
    /** No action taken resolution */
    const RES_NOT = 0;
    /** Action taken resolution */
    const RES_ACT = 1;
    /** Too old resolution */
    const RES_OLD = 2;
    /** @var string[] Mapping of resolutions to a user-friendly string. */
    public static $RESOLUTIONS = [
        self::RES_NOT => 'Not an issue',
        self::RES_ACT => 'Action taken',
        self::RES_OLD => 'Too old',
    ];

    protected static function generateSchema() {
        return [
            'alert_date' => [static::T_NUM, null, 0],
            'assignee_type' => [static::T_ENUM, Assignee::$TYPES, Assignee::T_USER],
            'assignee' => [static::T_NUM, null, User::NONE],
            'content' => [static::T_OBJ, null, []],
            'search_id' => [static::T_NUM, null, 0],
            'state' => [static::T_ENUM, static::$STATES, self::ST_NEW],
            'resolution' => [static::T_ENUM, static::$RESOLUTIONS, self::RES_NOT],
            'escalated' => [static::T_BOOL, null, false],
            'content_hash' => [static::T_STR, null, ''],
            'renderer_data' => [static::T_OBJ, null, []]
        ];
    }

    protected function serialize(array $data) {
        $data['content'] = json_encode((object)$data['content']);
        $data['renderer_data'] = json_encode((object)$data['renderer_data']);
        $data['escalated'] = (bool)$data['escalated'];
        return parent::serialize($data);
    }

    protected function deserialize(array $data) {
        $data['content'] = (array)json_decode($data['content'], true);
        $data['renderer_data'] = (array)json_decode($data['renderer_data'], true);
        $data['escalated'] = (bool)$data['escalated'];
        return parent::deserialize($data);
    }

    /**
     * Get the Search associated with this Alert.
     * @return Search The associated Search
     */
    public function getSearch() {
        return SearchFinder::getById($this->obj['search_id']);
    }
}

/**
 * Class AlertFinder
 * Finder for Alerts.
 * @package FOO
 * @method static Alert getById(int $id, bool $archived=false)
 * @method static Alert[] getAll()
 * @method static Alert[] getByQuery(array $query=[], $count=null, $offset=null, $sort=[], $reverse=null)
 * @method static Alert[] hydrateModels($objs)
 */
class AlertFinder extends ModelFinder {
    public static $MODEL = 'Alert';

    public static function generateWhere($query) {
        list($where, $vals) = parent::generateWhere($query);
        $from = (array) Util::get($query, 'from');
        $to = (array) Util::get($query, 'to');
        if(count($from) > 0) {
            $where[] = '`alert_date` > ?';
            $vals[] = (int) $from[0];
        }
        if(count($to) > 0) {
            $where[] = '`alert_date` < ?';
            $vals[] = (int) $to[0];
        }
        return [$where, $vals];
    }

    /**
     * Get counts of New/In Progress Alerts.
     * @return array The counts of Alerts.
     * @throws DBException
     */
    public static function getActiveCounts() {
        list($sql, $vals) = static::generateQuery(
            ['state', 'COUNT(*) as count'],
            ['state' => [Alert::ST_NEW, Alert::ST_INPROG]],
            null, null, [], ['state']
        );
        $ret = [0, 0];

        foreach(DB::query(implode(' ', $sql), $vals) as $row) {
            $ret[$row['state']] = (int) $row['count'];
        }

        return $ret;
    }

    /**
     * Get a count of recent Alerts with a given hash & Search.
     * @param int $search_id The Search id.
     * @param string $hash The hash.
     * @param int $since The time threshold.
     * @return int A count of Alerts.
     * @throws DBException
     */
    public static function getRecentSearchHashCount($search_id, $hash, $since) {
        return self::countByQuery([
            'search_id' => $search_id,
            'content_hash' => $hash,
            'create_date' => [
                self::C_GT => $since
        ]]);
    }

    /**
     * Get a count of recent Alerts from a given Search.
     * @param int $search_id The Search id.
     * @param int $since The time threshold
     * @return int A count of Alerts.
     * @throws DBException
     */
    public static function getRecentSearchCount($search_id, $since) {
        return self::countByQuery([
            'search_id' => $search_id,
            'create_date' => [
                self::C_GT => $since,
        ]]);
    }

    /**
     * Get a count of recent Alerts.
     * @param int $from The lower time threshold
     * @param int $to The upper time threshold
     * @return int[] A count of Alerts from each Search.
     * @throws DBException
     */
    public static function getRecentSearchCounts($from, $to) {
        list($sql, $vals) = static::generateQuery(
            ['search_id', 'COUNT(*) as count'],
            ['create_date' => [
                self::C_GTE => $from,
                self::C_LT => $to,
        ]], null, null, [['count', self::O_DESC]], ['search_id']);

        return DB::query(implode(' ', $sql), $vals);
    }
}
