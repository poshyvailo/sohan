<?php
/**
 * Created by PhpStorm.
 * User: Manager
 * Date: 22.08.2017
 * Time: 15:54
 */

namespace app\modules\parser\models;


class xbet extends ParsingAbstractClass
{
    public $proxyauth;
    public $url;
    private $connections;
    /**
     * xbet2 constructor.
     */
    public function __construct()
    {
        parent::__construct(1);
        $this->url = 'https://1xbetua.com/line/Football/';
        $this->connections = 20;
        // time zone
        date_default_timezone_set('Etc/GMT-3');
    }
    public function getEvents($matches)
    {
        for ($i=0; $i<count($matches); $i=$i+$this->connections) {
            $tmpMatches = [];
            for ($j=0; $j<$this->connections && $j+$i<count($matches); $j++) {
                $tmpMatches[] = $matches[$j+$i];
            }
            $channels = $this->proceedUrls($tmpMatches);
            foreach ($channels as $key => $channel) {
                $html = curl_multi_getcontent($channel);
                if (!empty($html)) {
                    $json = json_decode(gzdecode($html));
                    if (isset($json->Value->O1) && isset($json->Value->O2)) {
                        $teams = $this->insertTeam([$json->Value->O1, $json->Value->O2]);
                        $leage = $this->insertLeage($json->Value->L, 1);
                        $xbetUrl = $this->createUrl($json->Value->LE, $json->Value->O1E, $json->Value->O2E, $json->Value->LI, $json->Value->CI);
                        $matche_id = $this->insertMatch($teams, $leage, date("Y-m-d H:i:s", $json->Value->S), 1, $this->url . $xbetUrl);
                        for ($j = 0; $j < count($json->Value->E); $j++) {
                            //уменшаем время работы цикла
                            if ($json->Value->E[$j]->T > 10) {
                                break;
                            }
                            if ($json->Value->E[$j]->T == 1 || $json->Value->E[$j]->T == 2 || $json->Value->E[$j]->T == 3 || $json->Value->E[$j]->T == 9 || $json->Value->E[$j]->T == 10) {
                                if (isset($json->Value->E[$j]->P)) {
                                    $this->insertEvents($matche_id, $json->Value->E[$j]->P, $this->insertEventName($json->Value->E[$j]->T, 1, null)
                                        , $json->Value->E[$j]->C, 1);
                                } else {
                                    $this->insertEvents($matche_id, null, $this->insertEventName($json->Value->E[$j]->T, 1, null)
                                        , $json->Value->E[$j]->C, 1);
                                }
                            }
                        }
                    }
                }
                curl_multi_remove_handle($this->mh, $channel);
                \phpQuery::unloadDocuments();
                curl_close($channel);
            }
        }
    }
    /**
     * @param $leages лиги
     * @return array матчи
     */
    public function getMatches($leages)
    {
        $matches = [];
        for ($i=0; $i<count($leages); $i=$i+$this->connections) {
            $tmpLeages = [];
            for ($j=0; $j<$this->connections && $j+$i<count($leages); $j++) {
                $tmpLeages[] = $leages[$j+$i];
            }
            /*echo '<pre>';
            print_r($tmpLeages);
            echo '</pre>';*/
            $channels = $this->proceedUrls($tmpLeages);
            foreach ($channels as $key => $channel) {
                $html = curl_multi_getcontent($channel);
                if (!empty($html)) {
                    $json = json_decode(gzdecode($html));
                    if (isset($json->Value)) {
                        foreach ($json->Value as $val) {
                            $matches[] = [
                                "href" => "https://1xbetua.com/LineFeed/GetGameZip?id={$val->CI}&lng=ru&cfview=0&isSubGames=true&GroupEvents=true&countevents=20"
                            ];
                        }
                    }
                }
                curl_multi_remove_handle($this->mh, $channel);
                \phpQuery::unloadDocuments();
                curl_close($channel);
            }
        }
        return $matches;
    }
    /**
     * @return array all leages
     */
    public function getLeages()
    {
        $urls = [];
        $url[] = [
            'href' => 'https://1xbetua.com/LineFeed/GetChampsZip?sport=1&tf=1000000&tz=3&country=1'
        ];
        $channels = $this->proceedUrls($url);
        foreach ($channels as $key => $channel) {
            $html = curl_multi_getcontent($channel);
            if ($html) {
                $json = json_decode(gzdecode($html));
                if (isset($json->Value)) {
                    foreach ($json->Value as $val) {
                        if (isset($val->LE)) {
                            $urls[] = [
                                'href' => "https://1xbetua.com/LineFeed/Get1x2_Zip?champs={$val->LI}&sports=1&count=50&tf=1000000&tz=3&mode=4&country=2",
                                'text' => $val->LE
                            ];
                        }
                    }
                }
            }
            curl_multi_remove_handle($this->mh, $channel);
            \phpQuery::unloadDocuments();
            curl_close($channel);
        }
        return $urls;
    }
    public function createUrl($leage, $team1, $team2, $LI, $CI)
    {
        $leage = str_replace('.','', $leage);
        $team1 = str_replace('.','', $team1);
        $team2 = str_replace('.','', $team2);
        $leage = str_replace('(','', $leage);
        $team1 = str_replace('(','', $team1);
        $team2 = str_replace('(','', $team2);
        $leage = str_replace(')','', $leage);
        $team1 = str_replace(')','', $team1);
        $team2 = str_replace(')','', $team2);
        $leage = str_replace('\'','', $leage);
        $team1 = str_replace('\'','', $team1);
        $team2 = str_replace('\'','', $team2);

        $url = $LI.'-';
        for ($i=0; $i<strlen($leage); $i++) {
            if ($leage[$i]!=' ') {
                $url = $url.$leage[$i];
            } else {
                $url = $url.'-';
            }
        }
        $url = $url.'/'.$CI.'-';
        for ($i=0; $i<strlen($team1); $i++) {
            if ($team1[$i]!=' ') {
                $url = $url.$team1[$i];
            } else {
                $url = $url.'-';
            }
        }
        $url = $url.'-';
        for ($i=0; $i<strlen($team2); $i++) {
            if ($team2[$i]!=' ') {
                $url = $url.$team2[$i];
            } else {
                $url = $url.'-';
            }
        }
        return $url.'/';
    }
}