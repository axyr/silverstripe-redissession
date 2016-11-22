<?php
/**
 * RedisSessionHandler
 * Use Redis to store sessions, so we can access them with for example node.js.
 * The session data is stored as json for easier parsing with other clients.
 * Some code borrowed from http://www.sitepoint.com/saving-php-sessions-in-redis/
 * Redis keys are stored as path:prefix:sessid,
 * but you can pick a key value pretty much whatever you want.
 * Requires predis/predis
 * https://github.com/nrk/predis
 */
namespace SilverStripe\Predis;

use Predis\Client;

class RedisSessionHandler {

    public $client;

    public $ttl = 1800;

    public $path = 'sessions';

    public $prefix = 'PHPSESSID';

    public $key_sep = ':';

    public function __construct($path = 'sessions', $prefix = 'PHPSESSID', $key_sep = ':') {
        $this->client = new Client();
        $this->ttl = ini_get('session.gc_maxlifetime');

        $this->path = $path;
        $this->prefix = $prefix;
        $this->key_sep = $key_sep;

        session_set_save_handler(
            array($this, "open"),
            array($this, "close"),
            array($this, "read"),
            array($this, "write"),
            array($this, "destroy"),
            array($this, "gc")
        );
    }

    /**
     * Returns the full key path which Redis uses.
     *
     * @return string
     */
    private function redisKeyPath() {
        return $this->path . $this->key_sep . $this->prefix . $this->key_sep;
    }

    /**
     * No action necessary because connection is injected in constructor and arguments are not applicable.
     *
     * @param $savePath
     * @param $sessionName
     */
    public function open($savePath, $sessionName) { }

    /**
     * Clears the Predis Client.
     */
    public function close() {
        $this->client = null;
        unset($this->client);
    }

    /**
     * Gets json_encoded Session data from Redis and encode it back to php's session encoding.
     *
     * @param $id
     *
     * @return string
     */
    public function read($id) {
        if (!$this->client) return null;

        $tmp = $_SESSION;
        $id = $this->redisKeyPath() . $id;

        $_SESSION = json_decode($this->client->get($id), true);
        $this->client->expire($id, $this->ttl);

        if (isset($_SESSION) && !empty($_SESSION) && $_SESSION != null) {
            $new_data = session_encode();
            $_SESSION = $tmp;

            return $new_data;
        } else {
            return "";
        }
    }

    /**
     * Writes Session json_encoded, so we can access the Session data also with other clients (like node.js)
     *
     * @param $id Session ID
     * @param $data Payload data.
     *
     * @return bool
     */
    public function write($id, $data) {
        if (!$this->client) return false;

        // Write payload data to session
        $tmp = $_SESSION;
        session_decode($data);
        $new_data = $_SESSION;
        $_SESSION = $tmp;

        // Write payload to Redis and set expiration
        $id = $this->redisKeyPath() . $id;
        $this->client->set($id, json_encode($new_data));
        $this->client->expire($id, $this->ttl);

        return true;
    }

    /**
     * Deletes a session by ID.
     *
     * @param $id Session ID
     */
    public function destroy($id) {
        if ($this->client)
            $this->client->del($this->redisKeyPath() . $id);
    }

    /**
     * No need to gc because of using expiration
     *
     * @param $maxLifetime
     */
    public function gc($maxLifetime) { }
}
