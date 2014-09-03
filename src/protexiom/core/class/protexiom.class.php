<?php

/* This file is part of the Jeedom Protexiom plugin.
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This software is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class protexiom extends eqLogic {
    /*     * *************************Attributs****************************** */

    private $_UpdateDate = '';
    private $_HwVersion = '';
    private $_SomfyAuthCookie = '';
    private $_SomfyHost = '';
    private $_SomfyPort = '';
    private $_WebProxyHost = '';
    private $_WebProxyPort = '';

    /*     * ***********************Methode static*************************** */

    /*public static function pull($_options) {
        $weather = weather::byId($_options['weather_id']);
        if (is_object($weather)) {
            $weather_xml = $weather->getWeatherFromYahooXml();
            $sunrise = $weather_xml['astronomy']['sunrise'];
            $sunset = $weather_xml['astronomy']['sunset'];
            if ((date('Hi') + 100) >= $sunrise && (date('Hi') + 100 ) < $sunset) {
                $search = 'sunrise';
            } else {
                $search = 'sunset';
            }
            foreach ($weather->getCmd() as $cmd) {
                if ($cmd->getConfiguration('data') == $search) {
                    $cmd->event(date('Hi'));
                }
            }
            $weather->reschedule();
        } else {
            $cron = cron::byClassAndFunction('weather', 'pull', $_options);
            if (is_object($cron)) {
                $cron->remove();
            }
            throw new Exception('Weather ID non trouvé : ' . $_options['weather_id'] . '. Tache supprimé');
        }
    }*/

    /*public static function cronHourly() {
        foreach (self::byType('weather') as $weather) {
            if ($weather->getIsEnable() == 1) {
                $cron = cron::byClassAndFunction('weather', 'pull', array('weather_id' => intval($weather->getId())));
                if (!is_object($cron)) {
                    $weather->reschedule();
                } else {
                    $c = new Cron\CronExpression($cron->getSchedule(), new Cron\FieldFactory);
                    try {
                        $c->getNextRunDate();
                    } catch (Exception $ex) {
                        $weather->reschedule();
                    }
                }
            }
        }
    }*/

    /*     * *********************Methode d'instance************************* */

    /*public function preUpdate() {
        if ($this->getConfiguration('city') == '') {
            throw new Exception(__('L\identifiant de la ville ne peut être vide', __FILE__));
        }
        $this->setCategory('heating', 1);
    }*/

    /*public function postInsert() {
        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Température', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'temp');
        $weatherCmd->setUnite('°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Humidité', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'humidity');
        $weatherCmd->setUnite('%');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Pression', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'pressure');
        $weatherCmd->setUnite('Pa');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Condition Actuelle', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'condition');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Vitesse du vent', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'wind_speed');
        $weatherCmd->setUnite('km/h');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Direction du vent', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'wind_direction');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Lever du soleil', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'sunrise');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Coucher du soleil', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '-1');
        $weatherCmd->setConfiguration('data', 'sunset');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Température Min', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '0');
        $weatherCmd->setConfiguration('data', 'low');
        $weatherCmd->setUnite('°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Température Max', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '0');
        $weatherCmd->setConfiguration('data', 'high');
        $weatherCmd->setUnite('°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Condition', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '0');
        $weatherCmd->setConfiguration('data', 'condition');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Température Min +1', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '1');
        $weatherCmd->setConfiguration('data', 'low');
        $weatherCmd->setUnite('°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Température Max +1', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '1');
        $weatherCmd->setConfiguration('data', 'high');
        $weatherCmd->setUnite('°C');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('numeric');
        $weatherCmd->save();

        $weatherCmd = new weatherCmd();
        $weatherCmd->setName(__('Condition +1', __FILE__));
        $weatherCmd->setEqLogic_id($this->id);
        $weatherCmd->setConfiguration('day', '1');
        $weatherCmd->setConfiguration('data', 'condition');
        $weatherCmd->setUnite('');
        $weatherCmd->setType('info');
        $weatherCmd->setSubType('string');
        $weatherCmd->save();
    }*/

    /*public function postSave() {
        $this->reschedule();
    }*/

    /*public function preRemove() {
        $cron = cron::byClassAndFunction('weather', 'pull', array('weather_id' => intval($this->getId())));
        if (is_object($cron)) {
            $cron->remove();
        }
    }*/

    /*public function toHtml($_version = 'dashboard') {
        if ($this->getIsEnable() != 1) {
            return '';
        }
        $_version = jeedom::versionAlias($_version);
        $weather = $this->getWeatherFromYahooXml();
        if (!is_array($weather)) {
            $replace = array(
                '#icone#' => '',
                '#id#' => $this->getId(),
                '#city#' => '',
                '#condition#' => '{{Impossible de récupérer la météo.Pas d\'internet ?}}',
                '#temperature#' => '',
                '#windspeed#' => '',
                '#humidity#' => '',
                '#pressure#' => '',
                '#sunrise#' => '',
                '#sunset#' => '',
                '#collectDate#' => '',
                '#background_color#' => $this->getBackgroundColor($_version),
                '#eqLink#' => $this->getLinkToConfiguration(),
                '#forecast#' => '',
            );
            return template_replace($replace, getTemplate('core', $_version, 'current', 'weather'));
        }
        $html_forecast = '';
        $forcast_template = getTemplate('core', $_version, 'forecast', 'weather');
        foreach ($weather['forecast'] as $forecast) {
            $replace = array(
                '#day#' => $forecast['day'],
                '#icone#' => $forecast['icone'],
                '#low_temperature#' => $forecast['low_temperature'],
                '#hight_temperature#' => $forecast['high_temperature'],
            );
            $html_forecast .= template_replace($replace, $forcast_template);
        }
        $replace = array(
            '#id#' => $this->getId(),
            '#icone#' => $weather['condition']['icone'],
            '#city#' => $weather['location']['city'],
            '#condition#' => $weather['condition']['text'],
            '#temperature#' => $weather['condition']['temperature'],
            '#windspeed#' => $weather['wind']['speed'],
            '#humidity#' => $weather['atmosphere']['humidity'],
            '#pressure#' => $weather['atmosphere']['pressure'],
            '#sunrise#' => $weather['astronomy']['sunrise'],
            '#sunset#' => $weather['astronomy']['sunset'],
            '#collectDate#' => $this->getCollectDate(),
            '#background_color#' => $this->getBackgroundColor($_version),
            '#eqLink#' => $this->getLinkToConfiguration(),
            '#forecast#' => $html_forecast,
        );
        return template_replace($replace, getTemplate('core', $_version, 'current', 'weather'));
    }*/

    /*public function getShowOnChild() {
        return true;
    }*/

    /*     * **********************Getteur Setteur*************************** */

    /*public function getCollectDate() {
        return $this->_collectDate;
    }*/

    /*public function setCollectDate($_collectDate) {
        $this->_collectDate = $_collectDate;
    }*/

}

class protexiomCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */


    /*     * *********************Methode d'instance************************* */

    /*
    public function dontRemoveCmd() {
        return true;
    }
     */

    public function execute($_options = array()) {
        return false;
        /*
        $eqLogic_weather = $this->getEqLogic();
        $weather = $eqLogic_weather->getWeatherFromYahooXml();

        if (!is_array($weather)) {
            sleep(1);
            $weather = $eqLogic_weather->getWeatherFromYahooXml();
            if (!is_array($weather)) {
                return false;
            }
        }

        if ($this->getConfiguration('day') == -1) {
            if ($this->getConfiguration('data') == 'condition') {
                return $weather['condition']['text'];
            }
            if ($this->getConfiguration('data') == 'temp') {
                return $weather['condition']['temperature'];
            }
            if ($this->getConfiguration('data') == 'humidity') {
                return $weather['atmosphere']['humidity'];
            }
            if ($this->getConfiguration('data') == 'pressure') {
                return $weather['atmosphere']['pressure'];
            }
            if ($this->getConfiguration('data') == 'wind_speed') {
                return $weather['wind']['speed'];
            }
            if ($this->getConfiguration('data') == 'wind_direction') {
                return $weather['wind']['direction'];
            }
            if ($this->getConfiguration('data') == 'sunrise') {
                return $weather['astronomy']['sunrise'];
            }
            if ($this->getConfiguration('data') == 'sunset') {
                return $weather['astronomy']['sunset'];
            }
        }

        if ($this->getConfiguration('data') == 'condition') {
            return $weather['forecast'][$this->getConfiguration('day')]['condition'];
        }
        if ($this->getConfiguration('data') == 'low') {
            return $weather['forecast'][$this->getConfiguration('day')]['low_temperature'];
        }
        if ($this->getConfiguration('data') == 'high') {
            return $weather['forecast'][$this->getConfiguration('day')]['high_temperature'];
        }
        return false;
        */
    }

    /*     * **********************Getteur Setteur*************************** */
}

?>