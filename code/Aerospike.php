<?php
/*
 * Copyright 2014 Daniel Sloof
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
*/

class Rubic_Cache_Backend_Aerospike
    extends Zend_Cache_Backend
        implements Zend_Cache_Backend_ExtendedInterface
{

    /**
     * Default option values.
     *
     * @var array
     */
    protected $_options = [
        'hosts' => [
            'local' => [
                'addr' => '127.0.0.1',
                'port' => 3000
            ]
        ],
        'namespace' => 'magento',
        'set'       => 'cache'
    ];

    /**
     * The aerospike client.
     *
     * @var Aerospike
     */
    protected $_client;

    /**
     * Creates the aerospike client.
     *
     * @param array $options
     * @return void
     */
    public function __construct($options = array())
    {
        parent::__construct($options);
        $this->_client = new Aerospike(['hosts' => $this->getOption('hosts')]);
    }

    /**
     * Closes the aerospike connection.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->_client->close();
    }

    /**
     * Gets the aerospike key from a cache ID.
     *
     * @param string $id
     * @return array
     */
    protected function _getKeyFromId($id)
    {
        return $this->_client->initKey(
            $this->getOption('namespace'),
            $this->getOption('set'),
            $id
        );
    }

    /**
     * Whether or not a record matches a specific tag.
     *
     * @TODO: Support cleaning old records.
     *
     * @param array $record
     * @param array $tags
     * @param string $mode
     * @return boolean
     */
    protected function _recordMatchesTags($record, $tags, $mode)
    {
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                return true;
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                return count(array_intersect($tags, $record['bins']['tags'])) !== 0;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                return count(array_intersect($tags, $record['bins']['tags'])) === 0;
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                return count(array_intersect($tags, $record['bins']['tags'])) === count($tags);
            case Zend_Cache::CLEANING_MODE_OLD:
                return false;
        }
    }

    /**
     * Clean some cache records.
     *
     * @TODO:
     *  - We store tags as an array.
     *  - Secondary indexes can only be created on primitive types.
     *  - Meaning we can't use a query to find specific tags.
     * For now, that means cleaning is pretty inefficient. Find a solution to
     * this soon.
     *
     * @param string $mode
     * @param array|string $tags
     * @return boolean
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        if($tags && !is_array($tags)) {
            $tags = [ $tags ];
        }
        $status = $this->_client->scan(
            $this->getOption('namespace'),
            $this->getOption('set'),
            function ($record) use ($tags, $mode) {
                if ($this->_recordMatchesTags($record, $tags, $mode)) {
                    $this->_client->remove($record['key']);
                }
            }
        );
        return $status === Aerospike::OK;
    }

    /**
     * Gets an aerospike record.
     *
     * @param type $id
     * @return array|boolean
     */
    protected function _get($id)
    {
        $status = $this->_client->get($this->_getKeyFromId($id), $record);
        switch ($status) {
            case Aerospike::OK:
                return $record;
            default:
                return false;
        }
    }

    /**
     * Loads a record.
     *
     * @param string $id
     * @param boolean $doNotTestCacheValidity
     * @return boolean|string
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        $data = $this->_get($id);
        return $data ? $data['bins']['data'] : false;
    }

    /**
     * Removes a record.
     *
     * @param string $id
     * @return boolean
     */
    public function remove($id)
    {
        $status = $this->_client->remove($this->_getKeyFromId($id));
        return $status === Aerospike::OK;
    }

    /**
     * Saves a record.
     *
     * @param string $data
     * @param string $id
     * @param array $tags
     * @param int $specificLifetime
     * @return boolean
     */
    public function save($data, $id, $tags = array(), $specificLifetime = 0)
    {
        $status = $this->_client->put($this->_getKeyFromId($id), [
            'data' => $data,
            'tags' => $tags,
            'time' => time(),
            'id'   => $id
        ], $specificLifetime);
        return $status === Aerospike::OK;
    }

    /**
     * Return an associative array of capabilities (booleans) of the backend.
     *
     * @return array
     */
    public function getCapabilities()
    {
        return [
            'automatic_cleaning' => false,
            'tags'               => true,
            'expired_read'       => false,
            'priority'           => false,
            'infinite_lifetime'  => true,
            'get_list'           => false
        ];
    }

    /**
     * Gets a list of tags stored in the backend.
     *
     * @TODO: Should not be extremely difficult to implement.
     *
     * @return boolean
     */
    public function getTags()
    {
        return false;
    }

    /**
     * Test if a cache is available or not (for the given id).
     *
     * @param  string $id Cache id
     * @return bool|int
     */
    public function test($id)
    {
        $data = $this->_get($id);
        return $data ? $data['bins']['time'] : false;
    }

    /**
     * Give (if possible) an extra lifetime to the given cache id.
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extraLifetime)
    {
        $data = $this->_get($id);
        return $data && $this->save(
            $data['bins']['data'],
            $id,
            $data['bins']['tags'],
            $data['metadata']['ttl'] + $extraLifetime
        );
    }

    /**
     * Return the filling percentage of the backend storage.
     *
     * @return int
     */
    public function getFillingPercentage()
    {
        return 0;
    }

    /**
     * Gets IDs for specific tags and mode.
     *
     * @TODO: Yes, we abuse the cleaning modes for this.
     *
     * @param string $mode
     * @param array $tags
     * @return array
     */
    protected function _getIds($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
        $ids = [];
        $this->_client->scan(
            $this->getOption('namespace'),
            $this->getOption('set'),
            function ($record) use (&$ids, $tags, $mode) {
                if ($this->_recordMatchesTags($record, $tags, $mode)) {
                    $ids[] = $record['bins']['id'];
                }
            }
        );
        return $ids;
    }

    /**
     * Get all IDs.
     *
     * @return array
     */
    public function getIds()
    {
        return $this->_getIds();
    }

    /**
     * Gets IDs matching any specified tags.
     *
     * @param array $tags
     * @return array
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        return $this->_getIds(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $tags);
    }

    /**
     * Gets IDs matching all  specified tags.
     *
     * @param array $tags
     * @return array
     */
    public function getIdsMatchingTags($tags = array())
    {
        return $this->_getIds(Zend_Cache::CLEANING_MODE_MATCHING_TAG, $tags);
    }

    /**
     * Gets IDs not matching specified tags.
     *
     * @param array $tags
     * @return array
     */
    public function getIdsNotMatchingTags($tags = array())
    {
        return $this->_getIds(Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG, $tags);
    }

    /**
     * Return an array of metadatas for the given cache id
     *
     * @param string $id
     * @return array
     */
    public function getMetadatas($id)
    {
        $data = $this->_get($id);
        if ($data) {
            return [
                'expire' => time() + $data['metadata']['ttl'],
                'tags'   => $data['bins']['tags'],
                'mtime'  => $data['bins']['time']
            ];
        }
        return false;
    }

}
