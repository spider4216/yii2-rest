<?php

namespace yii2lab\rest\domain\helpers;

use Yii;
use yii\helpers\ArrayHelper;
use yii2lab\domain\entities\RequestEntity;
use yii2lab\misc\enums\HttpMethodEnum;

class RouteHelper
{

    public static function allRoutes($from = null) {
        if($from == 'route') {
            return self::allFromRoutes();
        }
        if($from == 'restClient') {
            return self::allFromRestClient();
        }
        $all = self::allFromRestClient();
        if(empty($all)) {
            $all = self::allFromRoutes();
        }
        return $all;
    }

    public static function allFromRestClient() {

        $collection = Yii::$app->db->createCommand('
SELECT endpoint, method, request
FROM rest 
WHERE  (module_id = \'rest-'.API_VERSION_STRING.'\' AND favorited_at > 0)
ORDER BY endpoint')->queryAll();
        $list = [];
        foreach ($collection as $favorite) {

            $group = self::getGroup($favorite['endpoint']);
            $request = new RequestEntity();
            $request->uri = $favorite['endpoint'];
            $request->method = $favorite['method'];

            $favorite['request'] = unserialize($favorite['request']);
            if(!empty($favorite['request']['bodyKeys'])) {
                $request->data = array_combine($favorite['request']['bodyKeys'], $favorite['request']['bodyValues']);
            }
            if(!empty($favorite['request']['queryKeys'])) {
                $request->data = array_combine($favorite['request']['queryKeys'], $favorite['request']['queryValues']);
            }
            if(!empty($favorite['request']['headerKeys'])) {
                $request->headers = array_combine($favorite['request']['headerKeys'], $favorite['request']['headerValues']);
            }
            $list[$group][] = $request;
        }
        return $list;
    }

    private static function getGroup($url, $index = 0) {
        return explode(SL, $url)[$index];
    }

    public static function allFromRoutes() {
        $list = [];
        foreach (Yii::$app->urlManager->rules as $rule) {
            if($rule instanceof yii\web\UrlRule) {
                /** @var yii\web\UrlRule $rule */
                if($rule->name != API_VERSION_STRING) {
                    $verb = ArrayHelper::toArray($rule->verb);
                    /*$method = implode(',', $method);
                    $method = !empty($method) ? $method : 'GET';
                    $method = strtolower($method);*/
                    $url = str_replace(API_VERSION_STRING . SL, EMP, $rule->name);
                    $group = self::getGroup($url);
                    $request = new RequestEntity();
                    $request->uri = $url;
                    $request->method = $verb[0];
                    $list[$group][] = $request;
                }
            } elseif($rule instanceof yii\rest\UrlRule) {
                /** @var yii\rest\UrlRule $rule */
                foreach ($rule->controller as $url => $v) {
                    $url = str_replace(API_VERSION_STRING . SL, EMP, $url);
                    $group = self::getGroup($url);
                    foreach ($rule->patterns as $pk => $pv) {
                        $arr = explode(SPC, $pk);
                        $method = trim($arr[0]);
                        $method = strtolower($method);
                        $methodList = explode(',', $method);
                        if(!empty($methodList) && HttpMethodEnum::isValidSet($methodList)) {
                            $request = new RequestEntity();
                            $request->uri = $url;
                            $request->method = $method;
                            $list[$group][] = $request;
                        }
                    }
                }
            }
        }
        return $list;
    }
}
