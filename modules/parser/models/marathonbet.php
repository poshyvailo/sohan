<?php
/**
 * Created by PhpStorm.
 * User: Manager
 * Date: 22.08.2017
 * Time: 13:45
 */

namespace app\modules\parser\models;
use yii;

class marathonbet extends ParsingAbstractClass
{
    public $url;
    public $base;
    private $connections;
    /**
     * marathonbet2 constructor.
     */
    public function __construct()
    {
        parent::__construct(2);
        $this->url[] = [
            'href' => 'https://www.marathonbet.com/su/popular/Football/'
        ];
        $this->base = 'https://www.marathonbet.com';
        $this->connections = 20;
    }
    public function __destruct()
    {
        parent::__destruct(); // TODO: Change the autogenerated stub
    }
    public function getEvents()
    {
        $matches = Yii::$app->db
            ->createCommand('
                SELECT 
                 id,
                 parsing_url as href
                FROM `matches` 
                WHERE `bukid` = :bukid
                AND url IS NOT NULL
                AND `date` > NOW()', [
                ':bukid' => $this->bukid
            ])->queryAll();
        for ($i=0; $i<count($matches); $i=$i+$this->connections) {
            $tmpMatches = [];
            for ($j=0; $j<$this->connections && $j+$i<count($matches); $j++) {
                $tmpMatches[] = $matches[$j+$i];
            }
            $channels = $this->proceedUrls($tmpMatches);
            foreach ($channels as $key => $channel) {
                $html = curl_multi_getcontent($channel);
                if ($html) {
                    $document = \phpQuery::newDocument(gzdecode($html));
                    //победы
                    $eventsOdds = [];
                    $spans = pq($document)
                        ->find('.category-container')
                        ->find('tr.event-header')
                        ->find('td>span');
                    foreach ($spans as $span) {
                        $eventsOdds[] = pq($span)->text();
                    }
                    $tooltips = [];
                    $divTooltips = pq($document)
                        ->find('.category-container')
                        ->find('tr.coupone-labels')
                        ->find('th>div.tooltip');
                    foreach ($divTooltips as $divTooltip) {
                        $tooltips[] = trim(pq($divTooltip)->text());
                    }
                    if (count($eventsOdds) == count($tooltips)) {
                        $events = [];
                        foreach ($eventsOdds as $key2 => $val2) {
                            // спецом так что бы кеф никогда не мог быть меньше 1ци
                            if ($eventsOdds[$key2] == '—') {
                                $eventsOdds[$key2] = 0.1;
                            }
                            $events[] = [
                                'odd' => $eventsOdds[$key2],
                                'name' => $tooltips[$key2]
                            ];
                        }
                        foreach ($events as $key2 => $val2) {
                            $eventName = $this->insertEventName($val2['name'], 2, null);
                            $this->insertEvents($matches[$key+$i]['id'], null, $eventName, $val2['odd'], 2);
                        }
                    }
                    //тоталы
                    $trs = pq($document)
                        ->find('div[data-mutable-id="Block_3"]')
                        ->find('div[data-mutable-id="MG1_1178924229"]')
                        ->find('tr');
                    foreach ($trs as $tr) {
                        $tds = pq($tr)
                            ->find('td');
                        $iii = 0;
                        foreach ($tds as $td) {
                            $handicap = pq($td)->find('.coeff-handicap')->text();
                            $odd = pq($td)->find('.coeff-price')->text();
                            if ($odd == '') {
                                continue;
                            }
                            if ($iii % 2 == 0) {
                                //больше
                                $eventName = $this->insertEventName('тотал меньше', 2, null);
                                $this->insertEvents($matches[$key+$i]['id'], $this->clearHandicap($handicap), $eventName, $odd, 2);
                            } else {
                                //меньше
                                $eventName = $this->insertEventName('тотал больше', 2, null);
                                $this->insertEvents($matches[$key+$i]['id'], $this->clearHandicap($handicap), $eventName, $odd, 2);
                            }
                            $iii++;
                        }
                    }
                    //
                    \phpQuery::unloadDocuments();
                    gc_collect_cycles();
                }
                curl_multi_remove_handle($this->mh, $channel);
                curl_close($channel);
            }
        }
    }
    /**
     * @return array лиги
     */
    public function getLeages()
    {
        $leagesArray = [];
        $channels = $this->proceedUrls($this->url);
        foreach ($channels as $key => $channel) {
            $html = curl_multi_getcontent($channel);
            if ($html) {
                $document = \phpQuery::newDocument(gzdecode($html));
                $categores = pq($document)
                    ->find('.category-container');
                foreach ($categores as $category) {
                    $a = pq($category)
                        ->find('a.category-label-link');
                    $this->insertLeage(trim($a->text()), 2, $this->base . trim($a->attr('href')));
                }
                \phpQuery::unloadDocuments();
                gc_collect_cycles();
            }
            curl_multi_remove_handle($this->mh, $channel);
            curl_close($channel);
        }
        return $leagesArray;
    }

    /**
     * Матчи
     */
    public function getMatches()
    {
        //$matchesArray = [];
        $leages = Yii::$app->db
            ->createCommand('
                SELECT 
                 id,
                 parsing_url as href,
                 `name` as text
                FROM `leages` 
                WHERE `bukid` = :bukid
                AND parsing_url IS NOT NULL', [
                ':bukid' => $this->bukid
            ])->queryAll();
        for ($i=0; $i<count($leages); $i=$i+$this->connections) {
            $tmpLeages = [];
            for ($j=0; $j<$this->connections && $j+$i<count($leages); $j++) {
                $tmpLeages[] = $leages[$j+$i];
            }
            $channels = $this->proceedUrls($tmpLeages);
            foreach ($channels as $key => $channel) {
                $html = curl_multi_getcontent($channel);
                if ($html) {
                    $document = \phpQuery::newDocument(gzdecode($html));
                    $matches = pq($document)
                        ->find('.category-container')
                        ->find('tbody');
                    foreach ($matches as $match) {
                        $eventPage = pq($match)
                            ->attr('data-event-page');
                        $date = pq($match)
                            ->find('.date')
                            ->text();
                        //today name
                        $teams = [];
                        $names = pq($match)
                            ->find('.today-member-name');
                        foreach ($names as $name) {
                            $teams[] = trim(pq($name)->text());
                        }
                        if (!empty($teams[0])) {
                            $date = date('Y-m-d') . ' ' . trim($date);
                        }
                        $names = pq($match)
                            ->find('.member-name');
                        foreach ($names as $name) {
                            $teams[] = trim(pq($name)->text());
                        }
                        if (!empty($eventPage)) {
                            $idTeams = $this->insertTeam($teams);
                            $this->insertMatch($idTeams, $leages[$key+$i]['id'], $this->dateIntoMysqlDate(trim($date)),
                                2, $this->base . ($eventPage), $this->base . ($eventPage));
                        }
                    }
                    \phpQuery::unloadDocuments();
                    gc_collect_cycles();
                }
                curl_multi_remove_handle($this->mh, $channel);
                curl_close($channel);
            }
        }
        //return $matchesArray;
    }
    /**
     * @param $date string в формате сайта марафон
     * @return string в форме mysql
     */
    public function dateIntoMysqlDate($date)
    {
        $monthes = [
            'июл' => '07',
            'авг' => '08',
            'сен' => '09',
            'окт' => '10'
        ];
        preg_match("/^([0-9]{2})\s([а-я]{1,})\s([0-9]{2}:[0-9]{2})$/u", $date, $match);
        if (isset($match[1])) {
            return date('Y').'-'.$monthes[$match[2]].'-'.$match[1].' '.$match[3];
        } else {
            return $date;
        }
    }

    /**
     * @param $handicap string параметр тотала
     * @return string в нужном формате тот же параметр
     */
    public function clearHandicap($handicap)
    {
        $res = '';
        for ($i=0; $i<strlen($handicap); $i++) {
            if ($handicap[$i]!="(" && $handicap[$i]!=")") {
                $res = $res.$handicap[$i];
            }
        }
        return $res;
    }
}