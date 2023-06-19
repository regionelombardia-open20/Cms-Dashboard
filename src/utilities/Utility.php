<?php

namespace open20\cms\dashboard\utilities;

use open20\cms\dashboard\models\CmsWfRequest;
use yii\helpers\Html;
use Yii;
use open20\design\utility\DesignUtility;
use open20\amos\core\record\CachedActiveQuery;
use yii\db\Query;
use luya\admin\models\Property;
use yii\helpers\ArrayHelper;
use luya\cms\models\NavContainer;
use app\components\CmsMenu;
use open20\cms\dashboard\Module;
use app\modules\cms\models\Nav;
use app\modules\cms\models\NavItem;
use app\modules\cms\models\NavItemPage;

/**
 * Description of Utility
 *
 */
class Utility
{

    /**
     *
     */
    const MAP_PERMISSION_CONTAINER = [
        'REDATTORECMS' => ['default', 'footer'],
        'CAPOREDATTORECMS' => ['default', 'footer', 'system'],
    ];

    /**
     *
     */
    const BLACKLIST_ALIAS = [
        'news',
        'community',
        'dashboard',
        'dashboards',
        'sitemanagement',
        'collaborations',
        'design',
        'events',
        'event',
        'layout',
        'comments',
        'messages',
        'chat',
        'pages',
        'site',
        'c',
        'p',
        'userauthfrontend',
        'cwh',
        'planner',
        'notify',
        'assets',
        'ebikeassets',
        'admin',
        'amosadmin',
        'documenti',
        'sondaggi',
        'api_marketplace',
        'organizations',
        'organizzazioni',
        'preferenceuser',
        'utility',
        'socialauth',
        'attachments',
        'file',
        'files',
        'uploadfiles',
        'uploads',
        'offline',
        'download',
        'img',
        'svg',
        'less',
        'downloads',
        'discussioni',
        'slideshow',
        'mobilebridge',
        'videoconference',
        'webmeeting',
        'onlyoffice'
    ];

    /**
     *
     * @return array
     */
    public static function getAllCmsContainer()
    {
        $containers = (new \yii\db\Query())->from('cms_nav_container')
            ->andWhere(['is_deleted' => 0]);
        if (\Yii::$app->user->can('ADMIN')) {
//nothing
        } else if (\Yii::$app->user->can('CAPOREDATTORECMS')) {
            $containers->andWhere(['in', 'alias', self::MAP_PERMISSION_CONTAINER['CAPOREDATTORECMS']]);
        } else {
            $containers->andWhere(['in', 'alias', self::MAP_PERMISSION_CONTAINER['REDATTORECMS']]);
        }
        $containers->select(['id', new \yii\db\Expression("REPLACE(name, ' Container', '') as name"), 'alias']);

        return $containers->all();
    }

    /**
     *
     * @param type $name
     * @param type $parent_nav
     * @param type $query
     * @return \yii\data\ArrayDataProvider|\yii\db\Query
     */
    public static function getAdminMenuLuya($name, $parent_nav = 0, $query = false, $get = [], $searchAllPages = true)
    {

        $defaultLanguage = Yii::$app->db->createCommand('SELECT id FROM admin_lang where is_default=1')->queryOne();

        $queryMenu = (new \yii\db\Query())->from('cms_nav_container as c');

        $queryMenu->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_home',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.publish_till publish_till',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'i.timestamp_update',
            'i.timestamp_create',
            'i.update_user_id',
            'i.create_user_id',
            'red.value link',
            'red.target target',
            'n.parent_nav_id as parent_nav_id',
            'n.sort_index sort_index',
        ]);

        $QueryMenuClone = clone $queryMenu;
        
        if(!$parent_nav){
            $parent_nav = 0;
        }

        $queryMenu->leftJoin(
            'cms_nav n',
            "n.nav_container_id = c.id and n.parent_nav_id = $parent_nav and n.is_deleted = 0 and is_draft = 0")
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andWhere(['c.alias' => $name])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index');


        //clone della query per cercare non solo sul primo livello ma anche sui  figli, mosto il padre dei figli che fanno match
        $QueryMenuClone->leftJoin(
            'cms_nav n',
            "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0"
        )->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andWhere(['c.alias' => $name])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index');;

        if (!empty($get['DynamicModel'])) {
            if(!$searchAllPages){
                $QueryMenuClone = $queryMenu;
            }
            if (!empty($get['DynamicModel']['search'])) {
                $QueryMenuClone->andWhere(['like', 'i.title', $get['DynamicModel']['search']]);
            }

            if (!empty($get['DynamicModel']['type'])) {
                $QueryMenuClone->andWhere(['i.nav_item_type' => $get['DynamicModel']['type']]);
            }
            if (!empty($get['DynamicModel']['status'])) {
                $status = $get['DynamicModel']['status'];
                $subqueryRequest = new Query();
                $subqueryRequest->select('cms_nav_item.id')
                    ->from('cms_nav_item')
                    ->innerJoin('cms_wf_request', 'cms_nav_item.id = cms_wf_request.nav_item_id AND cms_wf_request.nav_id = cms_nav_item.nav_id AND processed = 0')
                    ->groupBy('cms_nav_item.id');
                if ($status == 1) {
                    $QueryMenuClone->andWhere(['n.is_offline' => 1]);
                    $QueryMenuClone->andWhere(['not in', 'i.id', $subqueryRequest]);
                } else if ($status == 2) {
                    $QueryMenuClone->andWhere(['in', 'i.id', $subqueryRequest]);
                    $QueryMenuClone->andWhere(['n.is_offline' => 1]);
                } else if ($status == 3) {
                    $QueryMenuClone->andWhere(['n.is_offline' => 0]);
                }
            }
            if (!empty($get['DynamicModel']['updated_by'])) {
                $QueryMenuClone->andWhere(['i.update_user_id' => $get['DynamicModel']['updated_by']]);
            }
            $resClone = $QueryMenuClone->all();
            $cloneNavItemsIds = [];
            foreach ($resClone as $item) {
                $cloneNavItemsIds[] = $item['nav_id'];
                $cloneNavItemsIds[] = $item['parent_nav_id'];
            }

            $queryMenu->andWhere(['in', 'i.nav_id', $cloneNavItemsIds]);
        }


        /*
          $menuCache = CachedActiveQuery::instance($queryMenu);
          $menuCache->cache();
          $allMenu = $menuCache->asArray()->all();
         */

        if ($query) {
            return $queryMenu;
        }
        $allMenu = $queryMenu->indexBy('nav_id')->all();

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $allMenu,
            'sort' => [
                'attributes' => ['title', 'offline', 'hidden', 'publish_from', 'nav_item_type'],
            ],
            'pagination' => false,
        ]);

        return $dataProvider;
    }

    /**
     *
     * @param type $name
     * @param type $parent_nav
     * @param type $query
     * @return \yii\data\ArrayDataProvider|\yii\db\Query
     */
    public static function getLuyaTemplate($query = false, $get = [])
    {

        $defaultLanguage = Yii::$app->db->createCommand('SELECT id FROM admin_lang where is_default=1')->queryOne();

        $queryMenu = (new \yii\db\Query())->from('cms_nav_container as c');

        $queryMenu->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index sort_index',
        ])
            ->leftJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.parent_nav_id = 0 and n.is_deleted = 0"
            )
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andWhere(['n.is_draft' => 1])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index');

        if (!empty($get['DynamicModel']) && !empty($get['DynamicModel']['search'])) {
            $queryMenu->andWhere(['like', 'i.title', $get['DynamicModel']['search']]);
        }
        /*
          $menuCache = CachedActiveQuery::instance($queryMenu);
          $menuCache->cache();
          $allMenu = $menuCache->asArray()->all();
         */

        if ($query) {
            return $queryMenu;
        }
        $allMenu = $queryMenu->indexBy('nav_id')->all();

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $allMenu,
            'sort' => [
                'attributes' => ['title', 'offline', 'hidden', 'publish_from', 'nav_item_type'],
            ],
            'pagination' => false,
        ]);

        return $dataProvider;
    }

    public static function getAdminMenuLuyaTree($name, $query = false, $get = [], $nav_content_type = null)
    {

        $defaultLanguage = Yii::$app->db->createCommand('SELECT id FROM admin_lang where is_default=1')->queryOne();

        $queryMenu = (new \yii\db\Query())->from('cms_nav_container as c');

        $queryMenu2 = (new \yii\db\Query())->from('cms_nav_container as c');
        $queryMenu3 = (new \yii\db\Query())->from('cms_nav_container as c');
        $queryMenu4 = (new \yii\db\Query())->from('cms_nav_container as c');
        $queryMenu5 = (new \yii\db\Query())->from('cms_nav_container as c');
        $queryMenu6 = (new \yii\db\Query())->from('cms_nav_container as c');

        $queryMenu->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index index',
            new \yii\db\Expression("null as title1"),
            new \yii\db\Expression("null as title2"),
            new \yii\db\Expression("null as title3"),
            new \yii\db\Expression("null as title4"),
            new \yii\db\Expression("null as title5"),
            new \yii\db\Expression("null as nav1"),
            new \yii\db\Expression("null as nav2"),
            new \yii\db\Expression("null as nav3"),
            new \yii\db\Expression("null as nav4"),
            new \yii\db\Expression("null as nav5"),
            new \yii\db\Expression("0 as index1"),
            new \yii\db\Expression("0 as index2"),
            new \yii\db\Expression("0 as index3"),
            new \yii\db\Expression("0 as index4"),
            new \yii\db\Expression("0 as index5"),
        ])
            ->innerJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0 and n.parent_nav_id = 0"
            )
            ->innerJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andFilterWhere(['c.alias' => $name])
            ->andFilterWhere(['i.nav_item_type' => $nav_content_type])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.parent_nav_id, n.sort_index');
        $queryMenu2->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index index',
            'concat("  ", ichild1.title) title1',
            new \yii\db\Expression("null as title2"),
            new \yii\db\Expression("null as title3"),
            new \yii\db\Expression("null as title4"),
            new \yii\db\Expression("null as title5"),
            'child1.id as nav1',
            new \yii\db\Expression("null as nav2"),
            new \yii\db\Expression("null as nav3"),
            new \yii\db\Expression("null as nav4"),
            new \yii\db\Expression("null as nav5"),
            "child1.sort_index as index1",
            new \yii\db\Expression("null as index2"),
            new \yii\db\Expression("null as index3"),
            new \yii\db\Expression("null as index4"),
            new \yii\db\Expression("null as index5"),
        ])
            ->innerJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0 and n.parent_nav_id = 0"
            )
            ->leftJoin('cms_nav child1', 'n.id = child1.parent_nav_id and child1.is_deleted = 0 and child1.is_draft = 0')
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild1', 'ichild1.nav_id = child1.id and ichild1.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andFilterWhere(['c.alias' => $name])
            ->andFilterWhere(['i.nav_item_type' => $nav_content_type])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index, child1.sort_index'); //, child2.sort_index, child3.sort_index, child4.sort_index, child5.sort_index');

        $queryMenu3->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index index',
            'concat("  ", ichild1.title) title1',
            'concat("    ", ichild2.title) title2',
            new \yii\db\Expression("null as title3"),
            new \yii\db\Expression("null as title4"),
            new \yii\db\Expression("null as title5"),
            'child1.id as nav1',
            'child2.id as nav2',
            new \yii\db\Expression("null as nav3"),
            new \yii\db\Expression("null as nav4"),
            new \yii\db\Expression("null as nav5"),
            "child1.sort_index as index1",
            "child2.sort_index as index2",
            new \yii\db\Expression("null as index3"),
            new \yii\db\Expression("null as index4"),
            new \yii\db\Expression("null as index5"),
        ])
            ->innerJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0 and n.parent_nav_id = 0"
            )
            ->leftJoin('cms_nav child1', 'n.id = child1.parent_nav_id and child1.is_deleted = 0 and child1.is_draft = 0')
            ->leftJoin('cms_nav child2', 'child1.id = child2.parent_nav_id and child2.is_deleted = 0 and child2.is_draft = 0')
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild1', 'ichild1.nav_id = child1.id and ichild1.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild2', 'ichild2.nav_id = child2.id and ichild2.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andFilterWhere(['c.alias' => $name])
            ->andFilterWhere(['i.nav_item_type' => $nav_content_type])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index, child1.sort_index, child2.sort_index'); //, child3.sort_index, child4.sort_index, child5.sort_index');

        $queryMenu4->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index index',
            'concat("  ", ichild1.title) title1',
            'concat("    ", ichild2.title) title2',
            'concat("      ", ichild3.title) title3',
            new \yii\db\Expression("null as title4"),
            new \yii\db\Expression("null as title5"),
            'child1.id as nav1',
            'child2.id as nav2',
            'child3.id as nav3',
            new \yii\db\Expression("null as nav4"),
            new \yii\db\Expression("null as nav5"),
            "child1.sort_index as index1",
            "child2.sort_index as index2",
            "child3.sort_index as index3",
            new \yii\db\Expression("null as index4"),
            new \yii\db\Expression("null as index5"),
        ])
            ->innerJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0 and n.parent_nav_id = 0"
            )
            ->leftJoin('cms_nav child1', 'n.id = child1.parent_nav_id and child1.is_deleted = 0 and child1.is_draft = 0')
            ->leftJoin('cms_nav child2', 'child1.id = child2.parent_nav_id and child2.is_deleted = 0 and child2.is_draft = 0')
            ->leftJoin('cms_nav child3', 'child2.id = child3.parent_nav_id and child3.is_deleted = 0 and child3.is_draft = 0')
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild1', 'ichild1.nav_id = child1.id and ichild1.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild2', 'ichild2.nav_id = child2.id and ichild2.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild3', 'ichild3.nav_id = child3.id and ichild3.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andFilterWhere(['c.alias' => $name])
            ->andFilterWhere(['i.nav_item_type' => $nav_content_type])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index, child1.sort_index, child2.sort_index, child3.sort_index'); //, child4.sort_index, child5.sort_index');


        $queryMenu5->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index index',
            'concat("  ", ichild1.title) title1',
            'concat("    ", ichild2.title) title2',
            'concat("      ", ichild3.title) title3',
            'concat("        ", ichild4.title) title4',
            new \yii\db\Expression("null as title5"),
            'child1.id as nav1',
            'child2.id as nav2',
            'child3.id as nav3',
            'child4.id as nav4',
            new \yii\db\Expression("null as nav5"),
            "child1.sort_index as index1",
            "child2.sort_index as index2",
            "child3.sort_index as index3",
            "child4.sort_index as index4",
            new \yii\db\Expression("null as index5"),
        ])
            ->innerJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0 and n.parent_nav_id = 0"
            )
            ->leftJoin('cms_nav child1', 'n.id = child1.parent_nav_id and child1.is_deleted = 0 and child1.is_draft = 0')
            ->leftJoin('cms_nav child2', 'child1.id = child2.parent_nav_id and child2.is_deleted = 0 and child2.is_draft = 0')
            ->leftJoin('cms_nav child3', 'child2.id = child3.parent_nav_id and child3.is_deleted = 0 and child3.is_draft = 0')
            ->leftJoin('cms_nav child4', 'child3.id = child4.parent_nav_id and child4.is_deleted = 0 and child4.is_draft = 0')
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild1', 'ichild1.nav_id = child1.id and ichild1.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild2', 'ichild2.nav_id = child2.id and ichild2.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild3', 'ichild3.nav_id = child3.id and ichild3.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild4', 'ichild4.nav_id = child4.id and ichild4.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andFilterWhere(['c.alias' => $name])
            ->andFilterWhere(['i.nav_item_type' => $nav_content_type])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index, child1.sort_index, child2.sort_index, child3.sort_index, child4.sort_index'); //, child5.sort_index');

        $queryMenu6->select([
            'c.id root',
            'c.alias alias',
            'c.name container',
            'i.nav_id nav_id',
            'n.is_offline offline',
            'n.publish_from publish_from',
            'n.is_hidden hidden',
            'i.id id0',
            'i.alias url',
            'i.title title',
            'i.nav_item_type_id',
            'i.nav_item_type',
            'red.value link',
            'red.target target',
            'n.sort_index index',
            'concat("  ", ichild1.title) title1',
            'concat("    ", ichild2.title) title2',
            'concat("      ", ichild3.title) title3',
            'concat("        ", ichild4.title) title4',
            'concat("          ", ichild5.title) title5',
            'child1.id as nav1',
            'child2.id as nav2',
            'child3.id as nav3',
            'child4.id as nav4',
            'child5.id as nav5',
            "child1.sort_index as index1",
            "child2.sort_index as index2",
            "child3.sort_index as index3",
            "child4.sort_index as index4",
            "child5.sort_index as index5",
        ])
            ->innerJoin(
                'cms_nav n',
                "n.nav_container_id = c.id and n.is_deleted = 0 and is_draft = 0 and n.parent_nav_id = 0"
            )
            ->leftJoin('cms_nav child1', 'n.id = child1.parent_nav_id and child1.is_deleted = 0 and child1.is_draft = 0')
            ->leftJoin('cms_nav child2', 'child1.id = child2.parent_nav_id and child2.is_deleted = 0 and child2.is_draft = 0')
            ->leftJoin('cms_nav child3', 'child2.id = child3.parent_nav_id and child3.is_deleted = 0 and child3.is_draft = 0')
            ->leftJoin('cms_nav child4', 'child3.id = child4.parent_nav_id and child4.is_deleted = 0 and child4.is_draft = 0')
            ->leftJoin('cms_nav child5', 'child4.id = child5.parent_nav_id and child5.is_deleted = 0 and child5.is_draft = 0')
            ->leftJoin('cms_nav_item i', 'i.nav_id = n.id and i.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild1', 'ichild1.nav_id = child1.id and ichild1.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild2', 'ichild2.nav_id = child2.id and ichild2.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild3', 'ichild3.nav_id = child3.id and ichild3.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild4', 'ichild4.nav_id = child4.id and ichild4.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item ichild5', 'ichild5.nav_id = child5.id and ichild5.lang_id = ' . $defaultLanguage['id'])
            ->leftJoin('cms_nav_item_redirect red', 'red.id = i.nav_item_type_id AND i.nav_item_type = 3')
            ->andFilterWhere(['c.alias' => $name])
            ->andFilterWhere(['i.nav_item_type' => $nav_content_type])
            ->andWhere(['c.is_deleted' => 0])
            ->andWhere(['is not', 'i.id', null])
            ->orderBy('root, n.sort_index, child1.sort_index, child2.sort_index, child3.sort_index, child4.sort_index, child5.sort_index');

        $finalQuery = new \yii\db\Expression("({$queryMenu
                        ->union($queryMenu2)
                        ->union($queryMenu3)
                        ->union($queryMenu4)
                        ->union($queryMenu5)
                        ->union($queryMenu6)
                        ->createCommand()->rawSql}) as sum");

        $newQuery = (new \yii\db\Query())
            ->select(['sum.root', new \yii\db\Expression("IF(sum.nav5 is null, IF(sum.nav4 is null, IF(sum.nav3 is null, IF(sum.nav2 is null, IF(sum.nav1 is null, sum.nav_id, sum.nav1), sum.nav2), sum.nav3), sum.nav4), sum.nav5) as nav0"),
                new \yii\db\Expression("IF(sum.title5 is null, IF(sum.title4 is null, IF(sum.title3 is null, IF(sum.title2 is null, IF(sum.title1 is null, sum.title, sum.title1), sum.title2), sum.title3), sum.title4), sum.title5) as title"),
                'sum.index', 'sum.index1', 'sum.index2', 'sum.index3', 'sum.index4', 'sum.index5'])
            ->from($finalQuery)
            ->orderBy('root, index, index1, index2, index3, index4, index5')
            ->groupBy('nav0, title');

        if (!empty($get['DynamicModel']) && !empty($get['DynamicModel']['search'])) {
            //   $newQuery->andWhere(['like', 'i.title', $get['DynamicModel']['search']]);
        }

        /*
          $menuCache = CachedActiveQuery::instance($queryMenu);
          $menuCache->cache();
          $allMenu = $menuCache->asArray()->all();
         */

        if ($query) {
            return $newQuery;
        }
        $allMenu = $newQuery->all();

        $dataProvider = new \yii\data\ArrayDataProvider([
            'allModels' => $allMenu,
            'sort' => [
                'attributes' => ['title', 'offline', 'hidden', 'publish_from', 'nav_item_type'],
            ],
            'pagination' => false,
        ]);

        return $dataProvider;
    }

    public static function getVersions($item_id)
    {
        $defaultLanguage = Yii::$app->db->createCommand('SELECT id FROM admin_lang where is_default=1')->queryOne();

        $query = (new \yii\db\Query())->from('cms_nav_item i')
            ->innerJoin('cms_nav_item_page p', 'i.id = p.nav_item_id')
            ->andWhere(['i.lang_id' => $defaultLanguage['id']])
            ->andWhere(['i.id' => $item_id])
            ->select('p.timestamp_create, p.id, i.nav_id, i.id item_id, p.version_alias, i.nav_item_type_id, p.create_user_id ver_create_user_id');
        return $query;
    }

    public static function getVersion($id)
    {
        $defaultLanguage = Yii::$app->db->createCommand('SELECT id FROM admin_lang where is_default=1')->queryOne();

        $query = (new \yii\db\Query())->from('cms_nav_item i')
            ->innerJoin('cms_nav_item_page p', 'i.id = p.nav_item_id')
            ->andWhere(['i.lang_id' => $defaultLanguage['id']])
            ->andWhere(['p.id' => $id])
            ->select('p.timestamp_create, p.id, i.nav_id, i.id item_id, p.version_alias, i.nav_item_type_id, p.create_user_id ver_create_user_id');

        return $query->one();
    }

    /**
     *
     * @param int $id
     * @return boolean
     */
    public static function deleteVersion($id)
    {
        try {
            \Yii::$app->db->createCommand('delete from cms_nav_item_page_block_item where nav_item_page_id = ' . $id)->execute();
            \Yii::$app->db->createCommand('delete from cms_nav_item_page where id = ' . $id)->execute();
            return true;
        } catch (\yii\db\Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     *
     * @param int $id
     * @param int $item_id
     * @return boolean
     */
    public static function deletePage($id, $item_id)
    {
        try {
            \Yii::$app->db->createCommand('delete from cms_nav_item_page_block_item where nav_item_page_id in (select id from cms_nav_item_page where nav_item_id = ' . $item_id . ')')->execute();
            \Yii::$app->db->createCommand('delete from cms_nav_item_page where nav_item_id = ' . $item_id)->execute();
            \Yii::$app->db->createCommand('delete from cms_nav_item where nav_id = ' . $id)->execute();
            \Yii::$app->db->createCommand('delete from cms_nav where id = ' . $id)->execute();
            return true;
        } catch (\yii\db\Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     *
     * @param int $id
     * @return boolean
     */
    public static function publishVersion($id, $item_id, $nav_id)
    {
        try {

            \Yii::$app->db->createCommand('update cms_nav_item set nav_item_type_id = ' . $id . ' where id = ' . $item_id)->execute();
            \Yii::$app->db->createCommand('update cms_nav set is_offline = 0 where id = ' . $nav_id)->execute();
            return true;
        } catch (\yii\db\Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     *
     * @param int $id
     * @return boolean
     */
    public static function unpublishNav($id)
    {
        try {
            \Yii::$app->db->createCommand('update cms_nav set is_offline = 1 where id = ' . $id)->execute();
            return true;
        } catch (\yii\db\Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     *
     * @param int $id
     * @param int $not
     * @return boolean
     */
    public static function publishPage($id, $not = 0)
    {
        try {

            \Yii::$app->db->createCommand('update cms_nav set is_offline = ' . $not . ' where id = ' . $id)->execute();
            return true;
        } catch (\yii\db\Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     *
     * @param int $id
     * @param int $not
     * @return boolean
     */
    public static function publishInMenu($id, $not = 0)
    {
        try {

            \Yii::$app->db->createCommand('update cms_nav set is_hidden = ' . $not . ' where id = ' . $id)->execute();
            return true;
        } catch (\yii\db\Exception $ex) {
            return false;
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function getOnlineVersion($item_id)
    {
        $defaultLanguage = Yii::$app->db->createCommand('SELECT id FROM admin_lang where is_default=1')->queryOne();

        $query = (new \yii\db\Query())->from('cms_nav as n')
            ->innerJoin('cms_nav_item i', 'n.id = i.nav_id and i.lang_id = ' . $defaultLanguage['id'])
            ->innerJoin('cms_nav_item_page p', 'i.nav_item_type_id = p.id and i.id = p.nav_item_id')
            ->andWhere(['i.id' => $item_id])
            ->select('p.*');

        return $query->one();
    }

    public static function getLinkTarget($row)
    {
        $link = $row['link'];
        if (!empty($row['target']) && is_numeric($row['target'])) {
            $link = self::recursiveLink($row['target']);
        } else if (!empty($row['link']) && is_numeric($row['link'])) {
            $link = self::recursiveLink($row['link']);
        }
        return $link;
    }

    public static function recursiveLink($target)
    {
        $link = '';
        $item = \luya\cms\models\NavItem::find()->andWhere(['nav_id' => $target])->one();
        if (!empty($item)) {
            if ($item->nav_item_type != 3) {
                $link = '/' . \Yii::$app->composition['langShortCode'] . '/' . $item->alias;
            } else {
                $link = self::recursiveLink($item->nav_item_type_id);
            }
        }
        return $link;
    }

    public static function getParentPages($container = 'default')
    {
        $pages[] = ['id' => 0, 'name' => Module::txt('Pagina di primo livello')];
        try {
            $menu = Utility::getAdminMenuLuya($container, 0, true);
            $parents = $menu->all();

            foreach ($parents as $v) {
                $pages[] = ['id' => $v['nav_id'], 'name' => $v['title']];
            }
        } catch (Exception $ex) {

        }
        return $pages;
    }

    public static function getAlias($title, $alias = null)
    {

        if (empty($alias)) {
            $alias = \yii\helpers\Inflector::slug($title);
        } else {
            $alias = \yii\helpers\Inflector::slug($alias);
        }

        $i = 0;
        $modules = \Yii::$app->getModules();
        while (!in_array($alias, self::BLACKLIST_ALIAS) && !array_key_exists($alia, $modules) && ((self::checkSeoSlug($alias) && self::checkNavSlug($alias)) == false) && $i < 1000) {
            $i++;
            $number = 1;
            if (strrpos($alias, '-') == (strlen($alias) - 1)) {
                $alias = substr($alias, 0, strlen($alias) - 1) . '-' . $number;
            } else if (preg_match('/\-([0-9]*)$/', $alias, $res)) {
                $number = end($res);
                $pos = strrpos($alias, $number);
                $alias = substr($alias, 0, $pos) . ($number + 1);
            } else {
                $alias = $alias . '-' . $number;
            }
        }

        return $alias;
    }

    /**
     *
     * @param int $id
     * @return \yii\db\Query
     */
    public static function getNavItem($id)
    {
        if (is_numeric($id)) {
            $query = (new \yii\db\Query())->from('cms_nav n')
                ->innerJoin('cms_nav_item i', 'n.id = i.nav_id and n.is_deleted = 0 and n.is_draft = 0')
                ->where(['n.id' => $id])->andWhere(['n.is_deleted' => 0])->andWhere(['n.is_draft' => 0]);

            return $query;
        }
        return null;
    }

    public static function checkSeoSlug($slug)
    {
        if (!class_exists('open20\amos\seo\AmosSeo')) {
            return true;
        }
        $seoModule = \Yii::$app->getModule(\open20\amos\seo\AmosSeo::getModuleName());
        if (empty($seoModule)) {
            return true;
        }
        $count = \open20\amos\seo\models\SeoData::find()->andWhere(['pretty_url' => $slug])->count();
        return (($count > 0) ? false : true);
    }

    public static function checkNavSlug($slug)
    {
        $count = (new \yii\db\Query())->from('cms_nav_item i')
            ->where(['alias' => $slug])
            ->count();
        return (($count > 0) ? false : true);
    }

    public static function getUserOpenFromCms($user_id)
    {
        $userCms = (new \yii\db\Query())->from('admin_user')
            ->where(['id' => $user_id])
            ->select('email')
            ->one();
        return $userCms;
    }

    public static function getUserCmsFromOpenUser($email)
    {
        $userCms = (new \yii\db\Query())->from('admin_user')
            ->where(['email' => $email])
            ->one();
        return $userCms;
    }

    /**
     *
     * @return string
     */
    public static function generateRandomHash($bytes = 60)
    {
        $str = random_bytes($bytes);
        return bin2hex($str);
    }

    /**
     *
     * @param type $item_id
     * @param type $page_id
     * @return type
     */
    public static function checkWfRequest($item_id, $page_id)
    {
        $requests = 0;
        $item = NavItem::findOne($item_id);
        if ($item->nav_item_type == 1) {
            $requests = \open20\cms\dashboard\models\CmsWfRequest::find()
                ->andWhere(['nav_id' => $item->nav_id])
                ->andWhere(['nav_item_id' => $item_id])
                ->andWhere(['nav_item_page_id' => $page_id])
                ->andWhere(['processed' => 0])
                ->count();
        } else {
            $requests = \open20\cms\dashboard\models\CmsWfRequest::find()
                ->andWhere(['nav_id' => $item_id->nav_id])
                ->andWhere(['processed' => 0])
                ->count();
        }
        return $requests;
    }

    /**
     *
     * @param int $item_id
     * @return \yii\db\ActiveQuery
     */
    public static function getMyDraft($item_id, $array = false)
    {
        $request = \open20\cms\dashboard\models\CmsWfRequest::find()
            ->andWhere(['nav_id' => $item_id])
            ->andWhere(['processed' => 0])
            ->andWhere(['from_user' => \Yii::$app->user->id]);

        $user = \open20\amos\core\user\User::findOne(\Yii::$app->user->id);

        $userCms = self::getUserCmsFromOpenUser($user->email);

        $bozza = NavItemPage::find()->andWhere(['create_user_id' => $userCms['id']])
            ->andWhere(['nav_item_id' => $item_id])
            ->andWhere(['not in', 'nav_item_id', $request->select('nav_item_id')])
            ->orderBy('id desc');

        if ($array) {
            return $bozza->asArray()->one();
        }

        return $bozza->one();
    }

    public function getImageNavArray()
    {
        $images = [];
        $navitems = NavItem::find()
            ->innerJoin('cms_nav', 'cms_nav_item.nav_id = cms_nav.id')
            ->andWhere(['cms_nav.is_deleted' => 0])
            ->andWhere(['cms_nav.is_draft' => 1])
            ->andWhere(['cms_nav.nav_container_id' => 1])
            ->all();
        $images[0] = Module::txt('Nessun modello');
        foreach ($navitems as $k => $v) {
            $url = '/img/img_default.jpg';
            $image = $v->hasOneFile('seo_image')->one();
            if (!is_null($image)) {
                $url = $image->getWebUrl('square_medium', false, true);
            }
            $images[$v->nav_id] = Html::img($url, ['class' => 'img-responsive', 'alt' => Module::txt('Anteprima del modello')]) . ' ' . '<span>' . $v->title . '</span>';
        }
        return $images;
    }

    /**
     * @param $model
     * @return string
     */
    public static function getStatusPage($nav_id, $nav_item_id, $offline, $onlyText = false, $showPublishedLabel = true)
    {
        $status = '';
        $statusHtml = '';

        $countRequest = CmsWfRequest::find()->andWhere([
            'nav_id' => $nav_id,
            'nav_item_id' => $nav_item_id,
            'processed' => 0
        ])->count();

        $statusOnline = Module::txt('Pubblicato');
        $statusOffline = Module::txt('Bozza');

        if ($offline) {
            if ($countRequest > 0) {
                $statusOffline = Module::txt('In richiesta di pubblicazione');
                $statusHtml = ('<span class="mdi mdi-cloud-upload-outline text-muted mdi-24px" title="' . $statusOffline . '" data-toggle="tooltip"></span>');
            } else {
                $statusHtml = ('<span class="mdi mdi-cloud-off-outline mdi-24px text-muted" title="' . $statusOffline . '" data-toggle="tooltip"></span>');
            }
            $status = $statusOffline;
        } else {
            if ($showPublishedLabel) {
                $status = $statusOnline;
                $statusHtml = ('<span class="mdi mdi-cloud mdi-24px text-primary" title="' . $statusOnline . '" data-toggle="tooltip"></span>');
            }
        }

        if ($onlyText) {
            return $status;
        }
        return $statusHtml;
    }

    /**
     * @param $hidden
     * @return string
     */
    public static function getVisibilityIcon($hidden)
    {
        if ($hidden) {
            $labelHidden = Module::txt('Nascosto nel menu');
            return '<span class="mdi mdi-eye-off-outline mdi-24px text-muted" title="' . $labelHidden . '" data-toggle="tooltip"></span>';
        }
        $labelVisible = Module::txt('Visibile nel menu');
        return '<span class="mdi mdi-eye mdi-24px text-primary" title="' . $labelVisible . '" data-toggle="tooltip"></span>';
    }

    /**
     * @param $nav_item_title string
     * @param $nav_item_is_home bool
     * @return string
     */
    public static function getTitle($nav_item_title, $nav_item_is_home)
    {
        if ($nav_item_is_home) {
            $iconTitle = Module::txt("Homepage");
            $homeIcon = '<span class="mdi mdi-home text-info m-r-5" title="' . $iconTitle . '" data-toggle="tooltip"></span>';
            $title = $homeIcon . '<strong>' . $nav_item_title . '</strong>';
        } else {
            $title = '<strong>' . $nav_item_title . '</strong>';
        }

        return $title;

    }

    /**
     * @return array
     */
    public static function getTipologie()
    {
        return [
            1 => Module::txt('Redazionale'),
//            2 => Module::txt('Modulo'),
            3 => Module::txt('Redirect')
        ];
    }

    /**
     * @param $model
     * @return mixed|string
     */
    public static function getTipologia($nav_item_type)
    {
        $tipologie = self::getTipologie();
        if (!empty($tipologie[$nav_item_type])) {
            return $tipologie[$nav_item_type];
        }
        return Module::txt('Redazionale');
    }

    /**
     * @return mixed
     */
    public static function getActiveHomepage()
    {
        return Nav::find()
            ->andWhere(['is_home' => true])
            ->andWhere(['is_draft' => false])
            ->andWhere(['is_deleted' => false])
            ->andWhere(['is_offline' => false])
            ->one();
    }

    /**
     * @param $nav_id integer
     * @return void
     */
    public static function setHomepage($nav_id)
    {
        $activeHomepage = Utility::getActiveHomepage();
        if ($activeHomepage && $activeHomepage->id != $nav_id) {
            $activeHomepage->is_home = 0;
            $activeHomepage->save(false);
            \Yii::$app->cache->flush();
        }
    }

    /**
     * @param $nav Nav
     * @return bool
     */
    public static function canSetHomepage($nav)
    {
        $nav = Nav::findOne($nav->id);
        if (!$nav->is_draft && !$nav->is_offline && !$nav->is_deleted) {
            return true;
        }
        return false;
    }

}
