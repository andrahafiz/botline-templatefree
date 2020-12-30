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
        $data = $this->db->table('template')
            ->get()
            ->first();

        if ($data) {
            return (array) $data;
        }

        return null;
    }



    function isAnswerEqual(int $number, string $answer)
    {
        return $this->db->table('questions')
            ->where('number', $number)
            ->where('answer', $answer)
            ->exists();
    }
}
