<?php

use Classes\Filters;
use Classes\StaticFunctions as SF;
use Classes\UserData;

class Admin extends \Classes\DefaultClass
    {
        public function renderDefault(): void
        {
            $this->isLoggedIn();
            $this->setActiveMenu(self::MENU_ADMIN);

            $mySettings = $this->container->getSettingsManager()->getMy();
            if (isset($mySettings))
            {
                $this->addData('settings', json_decode($mySettings->getSettings(), true));
            }
            $this->renderLayout('index');
        }

        public function renderSaveSettings(): void
        {
            $data = $this->normalizeSettingsPayload($this->getParameter());
            if (!empty($data))
            {
                $mySettings = $this->container->getSettingsManager()->getMy();

                if (is_null($mySettings))
                {
                    $mySettings = new Classes\Settings\Settings();
                    $mySettings->setUser(UserData::getUserID());
                    $mySettings->setSettings(json_encode($data));
                } else  {
                    $mySettings->setSettings(json_encode($data));
                }
                $this->container->getSettingsManager()->saveEntity($mySettings);
                SF::addSuccessMessage('Setting was updated');
                SF::setHeader('/admin');
            } else {
                $this->container->getSettingsManager()->deleteMy();
                SF::addSuccessMessage('Setting was updated to default settings');
                SF::setHeader('/admin');
            }
        }

        private function normalizeSettingsPayload(array $data): array
        {
            unset($data['csrf_token']);

            foreach ($data as $key => $value)
            {
                if (is_array($value))
                {
                    $value = array_filter($value, static fn ($item) => !empty($item));
                    if (empty($value))
                    {
                        unset($data[$key]);
                        continue;
                    }
                } else if (empty($value)) {
                    unset($data[$key]);
                    continue;
                }

                $data[$key] = $value;
            }

            return $data;
        }

    }
