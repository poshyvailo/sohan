<?php
/**
 * Created by PhpStorm.
 * User: Manager
 * Date: 05.09.2017
 * Time: 17:15
 */

namespace app\modules\parser\models;


class leonbets extends ParsingAbstractClass
{
    public $url;
    private $connections;
    /**
     * leonbets constructor.
     */
    public function __construct() {
        parent::__construct(4);
        $this->connections = 20;
    }

    /**
     * @return array лиги
     */
    public function getLeages() {
        $leages = [];
        $url = [];
        $url[] = [
            'href' => $this->base
        ];
        $channels = $this->proceedUrls($url);
        foreach ($channels as $key => $channel) {
            $html = curl_multi_getcontent($channel);
            if ($html) {
                //$document = \phpQuery::newDocument(gzdecode($html));
                echo gzdecode($html);
                // sportlineMenu
                /*
                $menu = pq($document)
                    ->find('#sportlineMenu')
                    ->find('li:eq(1)')
                    ->text();
                echo $menu;
                */
                /*
                curl_multi_remove_handle($this->mh, $channel);
                \phpQuery::unloadDocuments();
                curl_close($channel);
                */
            }
        }
        return $leages;
    }
}