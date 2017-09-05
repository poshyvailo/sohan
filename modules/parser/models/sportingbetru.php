<?php
/**
 * Created by PhpStorm.
 * User: Manager
 * Date: 23.08.2017
 * Time: 14:23
 */

namespace app\modules\parser\models;


class sportingbetru extends ParsingAbstractClass
{
    private $connections;
    public function __construct()
    {
        parent::__construct(3);
        $this->useproxy = 0;
        $this->connections = 20;
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
                if ($html) {
                    $document = \phpQuery::newDocument(gzdecode($html));
                    //wins
                    $oddsArray = [];
                    $events = pq($document)->find('li.m_item');
                    foreach($events as $event) {
                        $headText = pq($event)
                            ->find('span.headerSub.groupHeader')
                            ->text();
                        $uls = pq($event)->find('ul.teamTieCoupon');
                        foreach ($uls as $ul) {
                            // коэфициенты
                            $priceArray = [];
                            $odds = pq($ul)
                                ->find('div.couponEvents')
                                ->find('div.odds');
                            foreach($odds as $odd) {
                                $price = pq($odd)
                                    ->find('#isOffered')
                                    ->find('span.priceText.EU')
                                    ->text();
                                $priceArray[] = $price;
                            }
                            // заголовки
                            $header = [];
                            $results = pq($ul)
                                ->find('.m_header')
                                ->find('.results')
                                ->find('.odds');
                            foreach ($results as $result) {
                                $header[] = trim(pq($result)->text());
                            }
                            $oddsArray[] = [
                                'groupName' => trim($headText),
                                'eventNames' => $header,
                                'odds' => $priceArray
                            ];
                        }
                    }
                    $leageId = $this->insertLeage($matches[$i + $key]['leage'], 3);
                    $idTeams = $this->insertTeam($matches[$i + $key]['name']);
                    $matchId = $this->insertMatch($idTeams, $leageId, $matches[$i + $key]['time'], 3, $matches[$key+$i]['href2']);
                    for($k=0; $k<count($oddsArray); $k++) {
                        $groupId = $this->insertEventGroupName($oddsArray[$k]['groupName'], 3);
                        for($m=0; $m<count($oddsArray[$k]['eventNames']); $m++) {
                            $eventName = $this->insertEventName($oddsArray[$k]['eventNames'][$m], 3, $groupId);
                            $this->insertEvents($matchId, null, $eventName, $oddsArray[$k]['odds'][$m], 3);
                        }
                    }
                    //totals
                    $totalsArray = [];
                    $items = pq($document)
                        ->find('li.m_item');
                    foreach ($items as $item) {
                        $headName = pq($item)
                            ->find('.headerSub.groupHeader')
                            ->text();
                        if ('Количество голов (90 минут)' == trim($headName)) {
                            $totals = pq($item)
                                ->find('.coupon.totals')
                                ->find('.results');

                            $k = 0;
                            foreach ($totals as $total) {
                                $price = pq($total)
                                    ->find('#isOffered')
                                    ->find('.priceText.EU')
                                    ->text();
                                $handicap = pq($total)
                                    ->find('#isOffered')
                                    ->find('.handicap')
                                    ->text();
                                if (!empty($price) && !empty($handicap)) {
                                    if ($k % 2 == 0) {
                                        $totalsArray[] = [
                                            'groupName' => trim($headName),
                                            'odds' => $price,
                                            'handicap' => $handicap
                                        ];
                                    } else {
                                        $totalsArray[] = [
                                            'groupName' => trim($headName),
                                            'odds' => $price,
                                            'handicap' => $handicap
                                        ];
                                    }
                                    $k++;
                                }

                            }
                        }
                    }
                    // запись значений
                    $leageId = $this->insertLeage($matches[$i + $key]['leage'], 3);
                    $idTeams = $this->insertTeam($matches[$i + $key]['name']);
                    $matchId = $this->insertMatch($idTeams, $leageId, $matches[$i + $key]['time'], 3, $matches[$key+$i]['href2']);
                    for($k=0; $k<count($totalsArray); $k++) {
                        $groupId = $this->insertEventGroupName($totalsArray[$k]['groupName'], 3);
                        if ($k % 2 == 0) {
                            $eventName = $this->insertEventName('Тотал больше', 3, $groupId);
                            $this->insertEvents($matchId, $totalsArray[$k]['handicap'], $eventName, $totalsArray[$k]['odds'], 3);
                        } else {
                            $eventName = $this->insertEventName('Тотал меньше', 3, $groupId);
                            $this->insertEvents($matchId, $totalsArray[$k]['handicap'], $eventName, $totalsArray[$k]['odds'], 3);
                        }
                    }
                }
                curl_multi_remove_handle($this->mh, $channel);
                \phpQuery::unloadDocuments();
                curl_close($channel);
            }
        }
    }

    public function getLeages()
    {
        $leages = [];
        $url[] = [
            'href' => $this->base.urlencode('/спорт-Футбол/0-102-410.html')
        ];
        $channels = $this->proceedUrls($url);
        foreach ($channels as $key => $channel) {
            $html = curl_multi_getcontent($channel);
            $document = \phpQuery::newDocument($html);
            $events = pq($document)
                ->find('div#events')
                ->find('div.box');
            foreach ($events as $event) {
                $as = pq($event)
                    ->find('div.dd')
                    ->find('a');
                foreach ($as as $a) {
                    $href = pq($a)->attr('href');
                    $name = pq($a)->text();
                    //echo $href.' '.$name.'<br>';
                    //
                    $arrayParamsForUrl = $this
                        ->parseUrl($href);
                    $url = $this->base . '/services/CouponTemplate.mvc/GetCoupon?couponAction=EVENTCLASSCOUPON' .
                        '&sportIds=' . $arrayParamsForUrl[1] .
                        '&marketTypeId=&eventId=&bookId=&' .
                        'eventClassId=' . $arrayParamsForUrl[2] .
                        '&sportId=' . $arrayParamsForUrl[1] . '&eventTimeGroup=';
                    if (('По ходу матча - Пятница'!=$name) && ('По ходу матча - Суббота'!=$name) && ('По ходу матча - Воскресенье'!=$name) && ('Купон на футбол'!=$name)) {
                        $leages[] = [
                            'text' => $name,
                            'href' => $url
                        ];
                    }
                }
            }
            curl_multi_remove_handle($this->mh, $channel);
            \phpQuery::unloadDocuments();
            curl_close($channel);
        }
        return $leages;
    }
    public function getMatches($leages)
    {
        $matches = [];
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
                    $events = pq($document)
                        ->find('div.couponEvents > div > div.columns > div.eventInfo');
                    foreach ($events as $event) {
                        //echo pq($event)->text().'<br>';
                        $eventName = pq($event)
                            ->find('div.eventName')
                            ->text();
                        $eventHref = pq($event)
                            ->find('div.eventName')
                            ->find('a')
                            ->attr('href');
                        $eventTime = pq($event)
                            ->find('span.StartTime')
                            ->text();
                        $arrayParamsForUrl = $this
                            ->parseUrl($eventHref);
                        if (isset($arrayParamsForUrl[3])) {
                            $url = $this->base . '/services/MarketTemplate.mvc/GetCoupon?couponAction=EVALLMARKETS&' .
                                'sportIds=' . $arrayParamsForUrl[1] . '&marketTypeId=&eventId=' . $arrayParamsForUrl[3]
                                . '&bookId=&eventClassId=' . $arrayParamsForUrl[2] . '&sportId=' . $arrayParamsForUrl[1] . '&eventTimeGroup=';
                            $matches[] = [
                                'name' => $this->getTeams($eventName),
                                'href' => $url,
                                'time' => $this->intoMysqlDate($eventTime),
                                'href2' => $eventHref,
                                'leage' => $leages[$i+$key]['text']
                            ];
                        }
                    }
                    curl_multi_remove_handle($this->mh, $channel);
                    //вот здесь сука утечка памяти уродский гугл
                    \phpQuery::unloadDocuments();
                }
            }
        }
        return $matches;
    }
    //pars url sport id ventClassId
    public function parseUrl($url)
    {
        $arrayExplode = explode('/',$url);
        $last = str_replace('.html','',$arrayExplode[count($arrayExplode)-1]);
        $array = explode('-',$last);
        return $array;
    }
    //date into mysql date
    // 05/06/2017 13:30 MSK -> 2017-06-05 13:30
    public function intoMysqlDate($date){
        $mysqlDate = $date[6].$date[7].$date[8].$date[9].'-';
        $mysqlDate = $mysqlDate.$date[3].$date[4].'-';
        $mysqlDate = $mysqlDate.$date[0].$date[1].' ';
        $mysqlDate = $mysqlDate.$date[11].$date[12].':';
        $mysqlDate = $mysqlDate.$date[14].$date[15];
        return $mysqlDate;
    }
    //getTeams
    public function getTeams($teams)
    {
        if(strpos($teams,' v ') === false) {
            $teamsArray = explode(' - ', $teams);
            for ($i = 0; $i < count($teamsArray); $i++) {
                $teamsArray[$i] = trim($teamsArray[$i]);
            }
        } else {
            $teamsArray = explode(' v ', $teams);
            for ($i = 0; $i < count($teamsArray); $i++) {
                $teamsArray[$i] = trim($teamsArray[$i]);
            }
        }
        return $teamsArray;
    }
}