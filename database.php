<?php

include_once 'config.php';

class Database
{
    public function __construct($host, $db_name, $db_username, $db_password, $charset)
    {
        $options = [
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ];

        $dsn = "mysql:host=$host;dbname=$db_name;charset=$charset";

        try {
            $pdo = new PDO($dsn, $db_username, $db_password, $options);
            $this->pdo = $pdo;
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }


    public function getData($sql)
    {
        $query = $this->pdo->prepare($sql);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }

    
}

