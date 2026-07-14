<?php
namespace Classes\User;

use Classes\Dibi;

class Manager extends  Dibi
{
    const TABLE = 'tUser';
    const PRIMARY = 'id';

    const EMAIL = 'email';
    const TOKEN = 'token';
    const LAST = 'lastSearch';
    const STATE = 'state';

    const ACTIVE = 1;

    const PERMISSION = 'permission';

    const P_GOD = 0;
    const P_USER = 1;

    const P_DICTIONARY = [
        self::P_GOD => 'Správce',
        self::P_USER => 'Uživatel'
    ];

    public function getAllByEmail(string $userName): ?User
    {
        $query = $this->db->select(self::ALL);
        $query->from(self::TABLE);
        $query->where('%n = %s', self::EMAIL, $userName);

        return $this->getEntity(self::class, (array) $query->fetch());
    }

    public function getByEmail(string $email): array
    {
        $query = $this->db->select(self::ALL);
        $query->from(self::TABLE);
        $query->where('%n = %s', self::EMAIL, $email);

        return (array) $query->fetch();
    }
}