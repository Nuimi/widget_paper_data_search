<?php
namespace Classes\Settings;

use Classes\Dibi;
use Classes\UserData;

class Manager extends  Dibi
{
    const TABLE = 'tSettings';
    const PRIMARY = 'id';

    const USER = 'FK_userID';
    const SETTINGS = 'settings';

    const JIF = 'jif';
    const AINF = 'ainf';
    const JIFP = 'jifPercentile';
    const IMPACT = 'impactMetrics';
    const INFLUENCE = 'influenceMetrics';
    const SOURCE = 'sourceMetrics';

    public function getMy(string $token = null)
    {
        $query = $this->db->select('s.%n', self::ALL);
        $query->from(self::TABLE)->as('s');
        if (is_null($token))
        {
            $query->where('s.%n = %i', self::USER, UserData::getUserID());
        } else {
            $query->leftJoin(\Classes\User\Manager::TABLE)->as('u')->on('s.%n = u.%n',
                self::USER, \Classes\User\Manager::PRIMARY);
            $query->where('u.%n = %s', \Classes\User\Manager::TOKEN, $token);
        }
        return $this->getEntity(self::class, (array) $query->fetch());
    }

    public function getMyDecoded(string $token = null): array
    {
        $settings = $this->getMy($token);
        if (is_null($settings) || empty($settings->getSettings()))
        {
            return [];
        }

        $decoded = json_decode($settings->getSettings(), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function deleteMy()
    {
        $this->db->delete(self::TABLE)->where("%n = %i", self::USER, UserData::getUserID())->execute();
    }
}
