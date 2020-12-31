<?php

namespace App\Gateway;

use Illuminate\Database\ConnectionInterface;

class TemplateGateway
{
    /**
     * @var ConnectionInterface
     */
    private $db;

    public function __construct()
    {
        $this->db = app('db');
    }

    // Question
    function getData()
    {
        $data = $this->db->select("select * from template limit 5");
        // $data = $this->db->table('template')->get()->first();
        if ($data) {
            return (array) $data;
        }

        return null;
    }
}
