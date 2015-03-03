<?php

namespace Leads;

use DB\PdoManager;

class Lead
{
    protected $db;
    protected $table_keys;
    protected $table_name = 'leads';
    protected $schema_name = 'app';

    public function __construct($id=null)
    {
        $this->db = PdoManager::instance('APP');
        $this->setTableKeys();

        if ($id) {
            $this->lead = $this->getLead();
        }
    }

    protected function setTableKeys()
    {
        $sql = "SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'app'
                AND COLUMN_NAME NOT IN ('id', 'date_created')
                AND TABLE_NAME = 'leads'";

        $this->table_keys = $this->db->fetchColumn($sql, array($this->table_name, $this->schema_name));
    }

    public function getLead($id)
    {
        $sql = "SELECT * FROM leads
                WHERE id = ?";
        return $this->db->fetchRow($sql, array($id));
    }

    public function create($lead)
    {
        $new_lead = array();
        foreach ($this->table_keys as $key) {
            if (!isset($lead[$key])) {
                return false;
            }
            $new_lead[$key] = $lead[$key];
        }

        if (!empty($new_lead)) {
            return $this->db->insert($this->table_name, $new_lead);
        }

        return false;
    }
}


?>