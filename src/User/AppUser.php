<?php

namespace User;

use DB\PdoManager;

class AppUser
{
    public $user;

    protected $id;
    protected $db;
    protected $table_keys = array();

    protected $table_name = 'users';
    protected $schema_name = 'app';

    public function __construct($id=null)
    {
        $this->id = $id;
        $this->db = PdoManager::instance('APP');

        $this->setTableKeys();

        // Set user if given
        if ($id) {
            $this->user = $this->getUser();
        }
    }

    protected function setTableKeys()
    {
        $sql = "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                AND TABLE_SCHEMA = ?
                AND COLUMN_NAME NOT IN ('id', 'date_created')";

        $this->table_keys = $this->db->fetchColumn($sql, array($this->table_name, $this->schema_name));
    }

    protected function hashPassword($password)
    {
        return hash('sha256', $password);
    }

    public function setUser($id)
    {
        if ($this->id != $id) {
            $this->id = $id;
            $this->user = $this->getUser();
        }
    }

    public function setUserByUsername($username)
    {
        $id = $this->db->fetchOne(
            "SELECT id
            FROM users
            WHERE username = ?",
            array($username)
        );
        if ($id) {
            $this->id = $id;
            $this->user = $this->getUser();
        } else {
            return false;
        }
    }

    public function getUser()
    {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->db->fetchRow($sql, array($this->id));
    }

    public function validatePassword($password)
    {
        return $this->hashPassword($password) === $this->user['password'];
    }

    public function update($updates)
    {
        // See if we have a user
        if (!$this->user) {
            return false;
        }

        // Make updates array
        $user_updates = array();
        foreach ($updates as $key => $update) {
            if (in_array($key, $this->table_keys)) {
                $user_updates[$key] = $update;
            }
        }

        // Update if any
        if (!empty($user_updates)) {
            return $this->db->update('users', $user_updates, $this->user['id']);
        }

        return true;
    }

    public function getLeads()
    {
        if (!$this->id) {
            return false;
        }

        $sql = "SELECT * FROM leads
                WHERE user_id = ?";

        return $this->db->fetchAll($sql, array($this->id));
    }

    public function create($user)
    {
        // Make sure all keys are given
        $user_insert = array();
        foreach ($this->table_keys as $key) {
            if (!isset($user[$key])) {
                return false;
            }

            $user_insert[$key] = $user[$key];
        }

        return $this->db->insert($this->table_name, $user_insert);
    }
}


?>

