<?php

namespace Survos\Scraper\Service;

use \PDO;
/**
 * Represents a key-value data store.
 * @license Apache 2.0
 *
 * also see https://stackoverflow.com/questions/47237807/use-sqlite-as-a-keyvalue-store
 * https://gist.githubusercontent.com/sbrl/c3bfbbbb3d1419332e9ece1bac8bb71c/raw/ef443cfde54e109719497a9e4ccadb2e74d5e609/StorageBox.php
 */
class StorageBoxService {
    /**
     * The SQLite database connection.
     * @var \PDO
     */
    private $db;

    /**
     * Initialises a new store connection.
     * @param	string	$filename	The filename that the store is located in.
     */
    function __construct(string $filename) {
        $firstrun = !file_exists($filename);
        $this->db = new \PDO("sqlite:" . realpath($filename)); // HACK: This might not work on some systems, because it depends on the current working directory
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if($firstrun) {
            $this->query("CREATE TABLE store (key TEXT UNIQUE NOT NULL, value TEXT)");
        }
    }
    /**
     * Makes a query against the database.
     * @param	string	$sql		The (potentially parametised) query to make.
     * @param	array	$variables	Optional. The variables to substitute into the SQL query.
     * @return	\PDOStatement		The result of the query, as a PDOStatement.
     */
    private function query(string $sql, array $variables = []) {
        // FUTURE: Optionally cache prepared statements?
        $statement = $this->db->prepare($sql);
        $statement->execute($variables);

        return $statement; // fetchColumn(), fetchAll(), etc. are defined on the statement, not the return value of execute()
    }

    /**
     * Determines if the given key exists in the store or not.
     * @param	string	$key	The key to test.
     * @return	bool	Whether the key exists in the store or not.
     */
    public function has(string $key) : bool {
        return $this->query(
                "SELECT COUNT(key) FROM store WHERE key = :key;",
                [ "key" => $key ]
            )->fetchColumn() > 0;
    }

    /**
     * Gets a value from the store.
     * @param	string	$key	The key to store the value under.
     * @return	string	The value to store.
     */
    public function get(string $key) : string {
        return $this->query(
            "SELECT value FROM store WHERE key = :key;",
            [ "key" => $key ]
        )->fetchColumn();
    }

    /**
     * Sets a value in the data store.
     * @param	string	$key	The key to set the value of.
     * @param	string	$value	The value to store.
     */
    public function set(string $key, string $value) : void {
        $this->query(
            "INSERT OR REPLACE INTO store(key, value) VALUES(:key, :value)",
            [
                "key" => $key,
                "value" => $value
            ]
        );
    }

    /**
     * Deletes an item from the data store.
     * @param	string	$key	The key of the item to delete.
     * @return	bool	Whether it was really deleted or not. Note that if it doesn't exist, then it can't be deleted.
     */
    public function delete(string $key) : bool {
        return $this->query(
                "DELETE FROM store WHERE key = :key;",
                [ "key" => $key ]
            )->rowCount() > 0;
    }

    /**
     * Empties the store.
     */
    public function clear() : void {
        $this->query("DELETE FROM store;");
    }
}
