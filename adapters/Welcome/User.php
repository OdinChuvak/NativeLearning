<?php

namespace adapters\Welcome;

use Exception;

class User implements \Service\Welcome\Contract\User
{
    const USERS = [
        1 => [
            'firstName' => 'Timur',
            'lastName' => 'Makhmudov',
            'birthDate' => '1990-03-30',
        ],
        2 => [
            'firstName' => 'Kamal',
            'lastName' => 'Magomedov',
            'birthDate' => '1989-02-14',
        ],
        3 => [
            'firstName' => 'Murad',
            'lastName' => 'Aleydarov',
            'birthDate' => '1991-12-07',
        ],
    ];

    /**
     * @throws Exception
     */
    public function getFirstName(int $userId): string
    {
        if (!array_key_exists($userId, self::USERS)) {
            throw new Exception('Пользователь не найден');
        }

        return self::USERS[$userId]['firstName'];
    }

    /**
     * @throws Exception
     */
    public function getLastName(int $userId): string
    {
        if (!array_key_exists($userId, self::USERS)) {
            throw new Exception('Пользователь не найден');
        }

        return self::USERS[$userId]['lastName'];
    }

    /**
     * @throws Exception
     */
    public function getBirthDate(int $userId): string
    {
        if (!array_key_exists($userId, self::USERS)) {
            throw new Exception('Пользователь не найден');
        }

        return self::USERS[$userId]['birthDate'];
    }
}
