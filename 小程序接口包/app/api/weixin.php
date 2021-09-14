<?php
/*
+--------------------------------------------------------------------------
|   WeCenter [#RELEASE_VERSION#]
|   ========================================
|   by WeCenter Software
|   © 2011 - 2014 WeCenter. All Rights Reserved
|   http://www.wecenter.com
|   ========================================
|   Support: WeCenter@qq.com
|
+---------------------------------------------------------------------------
*/


if (!defined('IN_ANWSION'))
{
    die;
}

define('IN_MOBILE', true);

class weixin extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = 'white';
        $rule_action['actions'] = array(
            'get_new_data',//首页 最新 推荐
            'get_hot_data',//首页 热门
            'index',//首页
            'user_ranks',//用户榜单
            'question_new_data',//问答首页
            'question_hot_data',//问答首页
            'question_unresponsive_data',//问答首页
            'get_answer_list',//问答回复列表
            'answer_detail',//回复详情
            'question_details',//问题详情
            'column_index_data',//专栏首页列表
            'column_index_article',//专栏首页文章列表
            'article_details',//文章详情
            'user',//他人个人中心
            'column_info',//专栏详情
            'get_category',//获取分类
            'get_nav',//获取导航
            'get_topics',//获取话题
            'get_topic_data',//话题首页
            'check_open_app',//判断是否开启配置

            'get_users_data',//首页 感兴趣的人
            'get_column_data',//首页 感兴趣的专栏
            'get_user_qu',//首页 关注 推荐用户
            'notification_list',//通知列表
            'inbox_list',//私信列表
            'inbox',//私信详情
            'privacy',//获取用户设置
            'question_invite_users_list',//问题邀请用户回答列表
            'get_report_reason',//获取举报理由
            'user_action_history',//用户参与
            'people',//用户个人中心
            'user_favorite',//用户收藏
            'get_my_column',//获取我的专栏
            'user_draft',//用户草稿
            'user_fans',//用户粉丝
            'user_follow',//用户关注
            'get_user_one_draft',//获取单条草稿
            'check_bind_app',//账号安全
            'get_topic_info',
            'get_topic_list'
        );

        return $rule_action;
    }

    public function setup()
    {
        
        $this->per_page = get_setting('contents_per_page');//每页总数

        if(!$this->user_id && $_SERVER['HTTP_TOKEN'])
        {   
            $this->user_id = AWS_APP::cache()->get($_SERVER['HTTP_TOKEN'])? :false;
            

            $this->user_info = $this->model('account')->get_user_info_by_uid($this->user_id, TRUE);

            $user_group = $this->model('account')->get_user_group($this->user_info['group_id'], $this->user_info['reputation_group']);

            if ($this->user_info['default_timezone'])
            {
                date_default_timezone_set($this->user_info['default_timezone']);
            }

            $this->model('online')->online_active($this->user_id, $this->user_info['last_active']);

            $this->user_info['group_name'] = $user_group['group_name'];
            
            $this->user_info['permission'] = $user_group['permission'];

            AWS_APP::session()->permission = $this->user_info['permission'];
        }

        HTTP::no_cache_header();
    }

    public function check_bind_app_action()
    {   
        $setting['mobile'] = $this->user_info['mobile'];

        $setting['email']  = $this->user_info['email'];

        if (get_setting('qq_login_enabled') == 'Y')
        {
            $setting['qq'] = $this->model('openid_qq')->get_qq_user_by_uid($this->user_id)['nickname']? :0;

        }else
        {
            $setting['qq'] = 0;
        }

        if (get_setting('sina_weibo_enabled') == 'Y')
        {   
            $setting['weibo'] = $this->model('openid_weibo_oauth')->get_weibo_user_by_uid($this->user_id)['name']? :0;
            
        }else
        {
            $setting['weibo'] = 0;
        }

        if (get_setting('weixin_app_id'))
        { 
            $setting['weixin'] = $this->model('openid_weixin_weixin')->get_user_info_by_uid($this->user_id)['nickname']? :0;

        }else
        {
            $setting['weixin'] = 0;
        }

        H::ajax_json_output(AWS_APP::RSM($setting? :[], 1, null));
    }


    public function check_open_app_action()
    {   
        $setting['qq'] = get_setting('qq_login_enabled') == 'Y'?1:0;

        $setting['weibo'] = get_setting('sina_weibo_enabled') == 'Y'?1:0;

        $setting['weixin'] = get_setting('weixin_app_id')?1:0;

        H::ajax_json_output(AWS_APP::RSM($setting? :[], 1, null));
    }


    public function get_my_column_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $column_list = $this->model('column')->get_column_by_uid($this->user_id);

        H::ajax_json_output(AWS_APP::RSM($column_list? :[], 1, null));
    }

    //首页数据  关注推荐
    public function get_user_qu_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $page = intval($_GET['page']);

        if($_GET['type'] == 'user')//关注的用户发布的问题与文章
        {
                $fids=$this->model('follow')->get_user_friends_ids($this->user_id); /*关注的人id集*/
                
                if(!$fids){

                    H::ajax_json_output(AWS_APP::RSM(null, 1, null));
                }

                $ret = $this->model('api')->get_newst($page,$fids,$this->user_id);

        }else if($_GET['type'] == 'column')//关注的专栏里的文章
        {       
                $cids=$this->model('api')->get_focus_column_ids_by_uid($this->user_id); /*关注的专栏id集*/ 
                
                if(!$cids){

                    H::ajax_json_output(AWS_APP::RSM(null, 1, null));
                }

                $ret = $this->model('api')->get_article_by_bids($cids,$page,$this->user_id);
                
        }else//关注的用户发布的问题与文章 与 关注的专栏里的文章
        {
                $fids=$this->model('follow')->get_user_friends_ids($this->user_id); /*关注的人id集*/
               
                $cids=$this->model('api')->get_focus_column_ids_by_uid($this->user_id); /*关注的专栏id集*/ 
                 
                if(!$fids && !$cids){

                    H::ajax_json_output(AWS_APP::RSM(null, 1, null));
                }

                $ret=$this->model('api')->get_ties_by_ids($cids,$fids,$page,$this->user_id);
        }


        H::ajax_json_output(AWS_APP::RSM($ret, 1, null));

    }



    /*
      首页-关注-感兴趣的人 (如果首页-关注的条数不足3条 则需调用-根据用户标签推荐感兴趣的人)
    */
    public function get_users_data_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $page=intval($_GET['page']);

        $focus_user=$this->model('account')->fetch_all('user_follow','fans_uid='.$this->user_id,'','','',["array_to_string(group_concat(friend_uid),',') as friend_uid"])[0]['friend_uid'];

        $focus_user=empty($focus_user)?0:$focus_user;

        $users= $this->model('account')->fetch_all('users','is_del!=1 and uid<>'.$this->user_id.' and uid NOT IN  ('.$focus_user.')','',3,$page*3,['user_name','uid','fans_count']);

        if($users){

            foreach ($users as $key => $value) {
                $users[$key]['avatar']=get_avatar_url($value['uid']);
                $users[$key]['focus_count']=$this->model('follow')->count('user_follow','fans_uid='.$this->user_id);
            }
        }

        H::ajax_json_output(AWS_APP::RSM($users, 1, null));

    }


    /*
      首页-关注-感兴趣的专栏 (如果首页-关注的条数不足3条 则需调用-根据热度文章数推荐感兴趣的专栏)
    */
    public function get_column_data_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $page=intval($_GET['page']);
        
        $columns= $this->model('api')->fetch_column_list($this->user_id,$page,3,'sum');

        H::ajax_json_output(AWS_APP::RSM($columns, 1, null));

    }

    //首页数据 最新 - 推荐
    public function get_new_data_action($limit = 10)
    {
        $category_id = intval($_GET['category_id']);
        $page = intval($_GET['page']) ? intval($_GET['page']) : 1;

        $posts_list = $this->model('posts')->get_posts_list(null, $page, get_setting('contents_per_page'), 'new', null, $category_id, null, $_GET['day'], $_GET['is_recommend']);

        $data = $this->model('api')->return_index_result($posts_list);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }

    //首页数据 热门
    public function get_hot_data_action()
    {
        $page = intval($_GET['page']) ? intval($_GET['page']) : 1;
        $categoryId = $_GET['category_id'];
        
        $hotList = $this->model('posts')->get_hot_posts(null, $categoryId, null, $_GET['day'], $page,get_setting('contents_per_page'));
        
        $data = $this->model('api')->return_index_result($hotList);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }


    //首页 默认最新
    public function index_action($limit = 10)
    {   
        $menu = array_values($this->model('api')->get_nav_menu_list_api('explore'));

        H::ajax_json_output(AWS_APP::RSM($menu, 1, null));

    }

    //话题首页数据 最新 - 问题 - 文章
    public function get_topic_data_action()
    {
        $page = intval($_GET['page']) ? intval($_GET['page']) : 1;

        $topic_ids = explode(',', $_GET['topic_id']);
        
        $posts_list = $this->model('posts')->get_posts_list($_GET['post_type']? :null, $page, get_setting('contents_per_page'), 'new',$topic_ids);

        $data = $this->model('api')->return_index_result($posts_list);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }

    /*用户榜单
    */
    public function user_ranks_action(){

        // $users_list = $this->model('account')->get_users_list(implode('', $where), calc_page_limit($_GET['page'], 3), true, false, 'reputation ASC');

        $users_list = $this->model('account')->get_users_list(implode('', $where), calc_page_limit($_GET['page'], get_setting('contents_per_page')), true, false, 'reputation DESC');

        if ($users_list)
        {
            foreach ($users_list as $key => $val)
            {
                $users_list[$key]['follow_check'] = $this->model('follow')->user_follow_check($this->user_id, $val['uid']);
            }
        }
        
        foreach ($users_list as $key => $val)
        {   
            $uids[] = $val['uid'];
        }

        if ($this->user_id)
        {
            $users_follow_check = $this->model('follow')->users_follow_check($this->user_id, $uids);
        }

        foreach ($users_list as $key => $val)
        {
            $users_list[$key]['focus'] = $users_follow_check[$val['uid']]?1:0;

            $users_list[$key]['avatar_file'] = get_avatar_url($val['uid'],'max');
        }

        $users_list = array_values($users_list);

        H::ajax_json_output(AWS_APP::RSM($users_list, 1, null));

    }


    //问答首页数据 最新
    public function question_new_data_action($limit = 10)
    {
        $category_id = intval($_GET['category_id']);
        $page = intval($_GET['page']) ? intval($_GET['page']) : 1;

        $posts_list = $this->model('posts')->get_posts_list('question', $page, get_setting('contents_per_page'), 'new', null, $category_id, null);

        $data = $this->model('api')->return_index_result($posts_list);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }

    //问答首页数据 热门
    public function question_hot_data_action()
    {
        $page = intval($_GET['page']) ? intval($_GET['page']) : 1;
        $categoryId = $_GET['category_id'];
        
        $hotList = $this->model('posts')->get_hot_posts('question', $categoryId, null, 30 , $page,get_setting('contents_per_page'));
        
        $data = $this->model('api')->return_index_result($hotList);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }


    //问答首页数据 等待回复
    public function question_unresponsive_data_action()
    {
        $category_id = intval($_GET['category_id']);
        $page = intval($_GET['page']) ? intval($_GET['page']) : 1;

        $posts_list = $this->model('posts')->get_posts_list('question', $page, get_setting('contents_per_page'), 'unresponsive', null, $category_id, null);

        $data = $this->model('api')->return_index_result($posts_list);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }


    /*
      问题详情
    */
    public function question_details_action($question_id = 0)
    {       
            $question_id=intval($_GET['question_id']);

            if(!$question_id)
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题ID不能为空')));

            // if (!$this->user_id AND !$this->user_info['permission']['visit_question'])
            // {   
            //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('没有权限访问')));
            // }

            if (! $question_info = $this->model('question')->fetch_row('question','question_id='.$question_id,'',['is_recommend','votes as praise','tread','question_id','question_content','question_detail','published_uid','is_del','add_time']))
            {   
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('问题不存在')));
            }

            if($question_info['is_del']==1)
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('问题已删除')));

            $user=$this->model('account')->fetch_row('users','uid='.$question_info['published_uid'],'',['uid','user_name','friend_count','fans_count']);

            $user['avatar']=get_avatar_url($user['uid']);
            
            if($this->user_id)
            {
                $user['focus']=$this->model('follow')->user_follow_check($this->user_id,$user['uid'])? :0;
            }else
            {
                $user['focus']=0;
            }
            

            $question_info['user_info'] = $user;

            $question_info['add_time'] = date_friendly($question_info['add_time']);

            $question_info['question_detail'] = replacePicUrl(html_entity_decode($question_info['question_detail']),base_url());

            $question_info['question_detail']=replaceVideoUrl(html_entity_decode($question_info['question_detail']),base_url());

            if($this->user_id){

                $question_info['question_thanks'] = $this->model('question')->get_question_thanks($question_id, $this->user_id)?1:0;

                $question_info['integral'] = ($this->model('account')->fetch_one('users','integral',"uid={$this->user_id}"));

                $question_info['focus']=$this->model('question')->has_focus_question($question_id,$this->user_id)? :-1;

                $question_info['report']=$this->model('account')->fetch_one('report','id',"uid={$this->user_id} and type='question' and target_id=$question_id and status=0")?1:0;

                $question_info['fav']=$this->model('favorite')->fetch_one('favorite','id',"uid={$this->user_id} and type='question' and item_id=$question_id")?1:0;
                
                if($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator'])
                {
                     $question_info['permission'] = 1;//管理员

                }else if($question_info['published_uid'] == $this->user_id)
                {
                     $question_info['permission'] = 2;//发起者

                }else
                {
                     $question_info['permission'] = 3;//普通用户
                }

            }else
            {   
                $question_info['question_thanks'] = 0;

                $question_info['focus'] = -1;

                $question_info['report'] = 0;

                $question_info['fav'] = 0;

                $question_info['permission'] = 0;//未登录

            }

            $question_info['invite_users'] = array_values($this->model('question')->get_invite_users($question_info['question_id']));

            $question_info['invite_users_number'] = count($question_info['invite_users']);

            $question_info['question_topics'] = $this->model('topic')->get_topics_by_item_id($question_info['question_id'], 'question');

            $this->model('question')->update_views($question_info['question_id']);


            $question_info['comment'] = $this->model('api')->get_question_comments($question_id,5,$page,$order); 
       
            $user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($question_info['comment'], 'uid'));

            foreach ($question_info['comment'] as $key => $value) {
                
                $comment_mes_info = $this->model('api')->parse_at_user($value['message'],false,false,true);

                $question_info['comment'][$key]['message']= $comment_mes_info['message'];

                $question_info['comment'][$key]['atuser'] = $comment_mes_info['atuser'];

                $question_info['comment'][$key]['user_name'] = $user_infos[$value['uid']]['user_name'];

                $question_info['comment'][$key]['avatar'] = get_avatar_url($value['uid'],'max');

                $question_info['comment'][$key]['time'] = date_friendly($question_info['comment'][$key]['time']);

            }
            
            H::ajax_json_output(AWS_APP::RSM($question_info, 1, null));

    }



    /*
      获取回复列表(排序)
      param order=1 时间排序 2热度排序
    */
    public function get_answer_list_action()
    {   
        $question_id=intval($_GET['question_id']);
        
        if(isset($_GET['answer_id']))
            $question_answer_id=intval($_GET['answer_id']);

        if($question_answer_id){
            
            $_answer=$this->model('api')->get_answers_by_ids((array)$question_answer_id);

            $question_info=$this->model('question')->fetch_row('question',"question_id=$question_id");

            foreach ($_answer as $key => $val){
                    
                    $users_info = $this->model('account')->get_user_info_by_uid($val['uid']);

                    $_answer[$key]['user_info'] = $users_info;

                    $_answer[$key]['user_info']['avatar'] = get_avatar_url($val['uid'],'max');

                    unset($_answer[$key]['ip']);

                    unset($_answer[$key]['user_info']['reg_ip']);

                    unset($_answer[$key]['user_info']['last_ip']);

                    $_answer[$key]['add_time']=date_friendly($val['add_time']);

                    $_answer[$key]['comments']['comments_info']=$this->model('api')->get_answer_comments($val['answer_id'],3);
                    $_answer[$key]['comments']['count']=$this->model('api')->count('answer_comments','is_del=0 and answer_id='.$val['answer_id']);
                    
                    if($this->user_id){

                        $_answer[$key]['fav']=$this->model('favorite')->fetch_one('favorite','id',"uid={$this->user_id} and type='answer' and item_id=$val[answer_id]")?1:0;

                        $rating=$this->model('account')->fetch_one('answer_vote','vote_value','answer_id='.$val['answer_id'].' and vote_uid='.$this->user_id);

                        $_answer[$key]['status']=$rating?($rating>0?1:-1):0;

                        $_answer[$key]['report']=$this->model('account')->fetch_one('report','id',"uid={$this->user_id} and type='question_answer' and target_id=$val[answer_id]")?1:0;

                        $_answer[$key]['user_rated_thanks'] = $this->model('answer')->users_rated('thanks', $val['answer_id'], $this->user_id)?1:0;

                        if($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator'])
                        {
                             $_answer[$key]['permission'] = 1;//管理员

                        }else if($val['uid'] == $this->user_id)
                        {
                             $_answer[$key]['permission'] = 2;//发起者

                        }else
                        {
                             $_answer[$key]['permission'] = 3;//普通用户
                        }

                    }else
                    {   

                        $_answer[$key]['user_rated_thanks'] = 0;
                        $_answer[$key]['fav'] = 0;
                        $_answer[$key]['status'] = 0;
                        $_answer[$key]['report'] = 0;
                        $_answer[$key]['permission'] = 0;
                    }

                    $_answer[$key]['answer_content']=strip_tags(htmlspecialchars_decode(html_entity_decode($val['answer_content'])));  

                    $_answer[$key]['answer_content'] = str_replace('&nbsp;','',$_answer[$key]['answer_content']);

                    $_answer[$key]['answer_content'] = str_replace('&nbsp','',$_answer[$key]['answer_content']);   

                    preg_match_all('/<img[^>]*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>/i',replacePicUrl(htmlspecialchars_decode(html_entity_decode($val['answer_content'])),base_url()),$match);

                    preg_match('/<video.*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>.*video>/i',replaceVideoUrl(htmlspecialchars_decode(html_entity_decode($val['answer_content'])),base_url()),$match2);

                    $_answer[$key]['video']=($match2[1]);

                    $_answer[$key]['imgs']=$match[1];
                

                $user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($_answer[$key]['comments']['comments_info'], 'uid'));
               
                foreach ($_answer[$key]['comments']['comments_info'] as $k1 => $v1) {

                    $comment_mes_info = $this->model('api')->parse_at_user($v1['message'],false,false,true);

                    $_answer[$key]['comments']['comments_info'][$k1]['message']= $comment_mes_info['message'];

                    $_answer[$key]['comments']['comments_info'][$k1]['atuser'] = $comment_mes_info['atuser'];

                    $_answer[$key]['comments']['comments_info'][$k1]['user_name'] = $user_infos[$v1['uid']]['user_name'];

                    $_answer[$key]['comments']['comments_info'][$k1]['avatar'] = get_avatar_url($v1['uid'],'max');

                    $_answer[$key]['comments']['comments_info'][$k1]['time'] = date_friendly($_answer[$key]['comments']['comments_info'][$k1]['time']);

                }
            }



            $_answer=$_answer[$question_answer_id];
        }

        $order=intval($_GET['order'])==1?'add_time desc':'(agree_count+against_count+thanks_count) desc';

         if($question_info=$this->model('question')->fetch_row('question',"question_id=$question_id"))
            $order="is_best desc,".$order;

       $page=intval($_GET['page']);

       if(!$question_id)
        H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题ID必传')));

        $question_answer_id=$question_answer_id?$question_answer_id:0;

        $answer_list = $this->model('api')->get_answer_list_by_question_id($question_id, 5, 'is_del= 0 and answer_id!='.$question_answer_id, $order , $page);

        foreach ($answer_list as $key => $val) {

                if($this->user_id){

                    $answer_list[$key]['fav']=$this->model('favorite')->fetch_one('favorite','id',"uid={$this->user_id} and type='answer' and item_id=$val[answer_id]")?1:0;

                    $rating=$this->model('account')->fetch_one('answer_vote','vote_value','answer_id='.$val['answer_id'].' and vote_uid='.$this->user_id);

                    $answer_list[$key]['status']=$rating?($rating>0?1:-1):0;

                    $answer_list[$key]['report']=$this->model('account')->fetch_one('report','id',"uid={$this->user_id} and type='question_answer' and target_id=$val[answer_id]")?1:0;

                    $answer_list[$key]['user_rated_thanks'] = $this->model('answer')->users_rated('thanks', $val['answer_id'], $this->user_id)?1:0;

                    if($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator'])
                    {
                         $answer_list[$key]['permission'] = 1;//管理员

                    }else if($val['uid'] == $this->user_id)
                    {
                         $answer_list[$key]['permission'] = 2;//发起者

                    }else
                    {
                         $answer_list[$key]['permission'] = 3;//普通用户
                    }

                }else
                {   
                    $answer_list[$key]['user_rated_thanks'] = 0;
                    $answer_list[$key]['fav'] = 0;
                    $answer_list[$key]['status'] = 0;
                    $answer_list[$key]['report'] = 0;
                    $answer_list[$key]['permission'] = 0;
                }
                
                preg_match('/<video.*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>.*video>/i',replaceVideoUrl(htmlspecialchars_decode(html_entity_decode($val['answer_content'])),base_url()),$match2);

                $answer_list[$key]['video']=($match2[1]);

                $answer_list[$key]['answer_content']=strip_tags(htmlspecialchars_decode(html_entity_decode($val['answer_content'])));

                $answer_list[$key]['answer_content'] = str_replace('&nbsp;','',$answer_list[$key]['answer_content']);

                $answer_list[$key]['answer_content'] = str_replace('&nbsp','',$answer_list[$key]['answer_content']);      

                preg_match_all('/<img[^>]*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>/i',replacePicUrl(htmlspecialchars_decode(html_entity_decode($val['answer_content'])),base_url()),$_match);

                $answer_list[$key]['imgs']=array_values($_match[1]);
            

            $user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($val['comments']['comments_info'], 'uid'));
   
            foreach ($val['comments']['comments_info'] as $k1 => $v1) {
                
                $comment_mes_info = $this->model('api')->parse_at_user($v1['message'],false,false,true);

                $answer_list[$key]['comments']['comments_info'][$k1]['message']= $comment_mes_info['message'];

                $answer_list[$key]['comments']['comments_info'][$k1]['atuser'] = $comment_mes_info['atuser'];

                $answer_list[$key]['comments']['comments_info'][$k1]['user_name'] = $user_infos[$v1['uid']]['user_name'];

                $answer_list[$key]['comments']['comments_info'][$k1]['avatar'] = get_avatar_url($v1['uid'],'max');

                $answer_list[$key]['comments']['comments_info'][$k1]['time'] = date_friendly($answer_list[$key]['comments']['comments_info'][$k1]['time']);

            }
        }
        
        $ret['answer_list']=$answer_list? :null;

        $ret['answer_top']= $_answer? :null;

         H::ajax_json_output(AWS_APP::RSM($ret, 1, null));

    }


    // /*
    //   获取回复详情(排序)
    //  param order=1 时间排序 2热度排序

    // */
    public function answer_detail_action()
    {
       $answer_id=intval($_GET['answer_id']);

       $page=intval($_GET['page']);

       $order=intval($_GET['order'])==1?'time desc':'time desc';

       if(!$answer_id)
        H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('回复ID必传')));

           $answer_info=$this->model('answer')->get_answer_by_id($answer_id);

           if(!$answer_info)
            H::ajax_json_output(AWS_APP::RSM(null, '-2', AWS_APP::lang()->_t('回复不存在')));

           if($answer_info['is_del']==1)
            H::ajax_json_output(AWS_APP::RSM(null, '-2', AWS_APP::lang()->_t('回复已删除')));

           $data['answer_info']['answer_id']=$answer_info['answer_id'];
            $question_info=$this->model('question')->fetch_row('question',"question_id=$answer_info[question_id]");
            
           $data['answer_info']['is_best']=$answer_info['is_best'];

           $data['answer_info']['answer_content']=replacePicUrl(htmlspecialchars_decode(html_entity_decode($answer_info['answer_content'])),base_url());

           $data['answer_info']['anonymous'] = $answer_info['anonymous'];

           $data['answer_info']['agree_count']=$answer_info['agree_count'];

           $data['answer_info']['against_count']=$answer_info['against_count'];

           $data['answer_info']['add_time']=date_friendly($answer_info['add_time']);

           $data['answer_info']['user_info']['uid']=$answer_info['uid'];

           $user=$this->model('account')->fetch_row('users','uid='.$answer_info['uid'],'',['uid','user_name']);

           $user['avatar']=get_avatar_url($user['uid']);
           
           if($this->user_id)
           {    
                $user['focus']=$this->model('follow')->user_follow_check($this->user_id,$user['uid'])?1:0;

                $rating=$this->model('article')->fetch_one('answer_vote','vote_value','answer_id='.$answer_id.' and vote_uid='.$this->user_id);

                $data['answer_info']['status']=$rating?($rating==1?1:-1):0;

                $data['answer_info']['report']=$this->model('account')->fetch_one('report','id',"uid={$this->user_id} and type='question_answer' and target_id=$answer_id and status=1")?1:0;

                $data['answer_info']['fav']=$this->model('favorite')->fetch_one('favorite','id',"uid={$this->user_id} and type='answer' and item_id=$answer_id")?1:0;

                $data['answer_info']['user_rated_thanks'] = $this->model('answer')->users_rated('thanks', $answer_id, $this->user_id)?1:0;

                if($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator'])
                {
                     $data['answer_info']['permission'] = 1;//管理员

                }else if($val['uid'] == $this->user_id)
                {
                     $data['answer_info']['permission'] = 2;//发起者

                }else
                {
                     $data['answer_info']['permission'] = 3;//普通用户
                }

           }else
           {    
                $user['focus']= 0;

                $data['answer_info']['user_rated_thanks'] = 0;

                $data['answer_info']['status'] = 0;

                $data['answer_info']['report'] = 0;

                $data['answer_info']['fav'] = 0;

                $data['answer_info']['permission'] = 0;

           } 

           

           $data['answer_info']['user_info']=$user;

       $data['answer_comment']=$this->model('api')->get_answer_comments($answer_id,3,$page,$order); 
       
       $user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($data['answer_comment'], 'uid'));

       foreach ($data['answer_comment'] as $key => $value) {
            
            $comment_mes_info = $this->model('api')->parse_at_user($value['message'],false,false,true);

            $data['answer_comment'][$key]['message']= $comment_mes_info['message'];

            $data['answer_comment'][$key]['atuser'] = $comment_mes_info['atuser'];

            $data['answer_comment'][$key]['user_name'] = $user_infos[$value['uid']]['user_name'];

            $data['answer_comment'][$key]['avatar'] = get_avatar_url($value['uid'],'max');

            $data['answer_comment'][$key]['time'] = date_friendly($data['answer_comment'][$key]['time']);

       }

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));

    }
    

    //通知列表
    public function notification_list_action()
    {
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $list = $this->model('notify')->list_notification_ali($this->user_id, $_GET['flag'], intval($_GET['page']) * $this->per_page . ', ' . $this->per_page);
        
        $this->model('account')->update_notification_unread($this->user_id);

        foreach ($list as $key => $value) {
                  
              $list[$key]['add_time'] = date_friendly($value['add_time'], 604800, 'm-d');

              // $list[$key]['message'] = $this->model('api')->removeLinks($value['title']);

              $list[$key]['read_flag_msg'] = $value['read_flag'] != 1?'标为已读':'已读';

        }

        $list = array_values($list);

        $list_data = null;
        
        if($this->user_info['inbox_unread'])
        {
            //最新消息id
            if ($dialog = $this->model('message')->fetch_row('inbox_dialog','recipient_uid = '.$this->user_id .' or sender_uid = '.$this->user_id,'update_time DESC'))
            {   
                
                if ($dialog_list = $this->model('message')->get_message_by_dialog_id($dialog['id'],'add_time DESC',0,1))
                {   
                    foreach ($dialog_list as $key => $value)
                    {   
                        if($value['uid'] != $this->user_id)
                        {   
                            $recipient_user = $this->model('account')->get_user_info_by_uid($value['uid'],false,true,true);

                            $value['message'] = html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['message'])));  
                           
                            $value['user_name'] = $recipient_user['user_name'];
                            $value['other_uid'] = $recipient_user['uid'];
                            $value['avatar_file'] = get_avatar_url($recipient_user['uid'], 'mid');
                            $value['add_time'] = date_friendly($value['add_time']);
                            $list_data = $value;
                        }
                        
                    }
                    
                }
            }
        }

        H::ajax_json_output(AWS_APP::RSM(array('list'=>$list? :null,'inbox_num'=>$this->user_info['inbox_unread']? :0,'msg' => $list_data? :['message' => '还未收到最新消息']), 1, null));
        
    }

    //私信列表
    public function inbox_list_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if ($inbox_dialog = $this->model('message')->get_inbox_message($_GET['page'], get_setting('contents_per_page'), $this->user_id))
        {
            foreach ($inbox_dialog as $key => $val)
            {
                $dialog_ids[] = $val['id'];

                if ($this->user_id == $val['recipient_uid'])
                {
                    $inbox_dialog_uids[] = $val['sender_uid'];
                }
                else
                {
                    $inbox_dialog_uids[] = $val['recipient_uid'];
                }
            }
        }

        if ($inbox_dialog_uids)
        {
            if ($users_info_query = $this->model('account')->get_user_info_by_uids($inbox_dialog_uids))
            {
                foreach ($users_info_query as $user)
                {
                    $users_info[$user['uid']] = $user;
                }
            }
        }



        if ($dialog_ids)
        {
            $last_message = $this->model('message')->get_last_messages($dialog_ids);
        }

        if ($inbox_dialog)
        {
            foreach ($inbox_dialog as $key => $value)
            {
                if ($value['recipient_uid'] == $this->user_id AND $value['recipient_count']) // 当前处于接收用户
                {
                    $data[$key]['user_name'] = $users_info[$value['sender_uid']]['user_name'];
                    $data[$key]['avatar_file'] = get_avatar_url($value['sender_uid']);
                    $data[$key]['url_token'] = $users_info[$value['sender_uid']]['url_token'];

                    $data[$key]['unread'] = $value['recipient_unread'];
                    $data[$key]['count'] = $value['recipient_count'];

                    $data[$key]['uid'] = $value['sender_uid'];
                }
                else if ($value['sender_uid'] == $this->user_id AND $value['sender_count']) // 当前处于发送用户
                {
                    $data[$key]['user_name'] = $users_info[$value['recipient_uid']]['user_name'];
                    $data[$key]['avatar_file'] = get_avatar_url($value['recipient_uid']);
                    $data[$key]['url_token'] = $users_info[$value['recipient_uid']]['url_token'];

                    $data[$key]['unread'] = $value['sender_unread'];
                    $data[$key]['count'] = $value['sender_count'];
                    $data[$key]['uid'] = $value['recipient_uid'];
                }

                $data[$key]['last_message'] = $last_message[$value['id']];
                $data[$key]['update_time'] = date_friendly($value['update_time']);
                $data[$key]['id'] = $value['id'];
            }
        }
        
        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
        
    }

    public function inbox_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $uid = intval($_GET['uid']);
        //查询之间是否有私信记录
        $dialog_id = $this->model('message')->fetch_one('inbox_dialog','id','(sender_uid = '.$this->user_id.' and recipient_uid = '.$uid.') or (sender_uid = '.$uid.' and recipient_uid = '.$this->user_id.')');

        if ($dialog_id)
        {
            if (!$dialog = $this->model('message')->get_dialog_by_id($dialog_id))
            {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', '指定的站内信不存在'));
            }

            if ($dialog['recipient_uid'] != $this->user_id AND $dialog['sender_uid'] != $this->user_id)
            {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', '指定的站内信不存在'));
            }

            $this->model('message')->set_message_read($dialog_id, $this->user_id);
           
            if ($list = $this->model('api')->get_message_by_dialog_id($dialog_id,'id DESC'))
            {
                if ($dialog['sender_uid'] != $this->user_id)
                {
                    $recipient_user = $this->model('account')->get_user_info_by_uid($dialog['sender_uid']);
                }
                else
                {
                    $recipient_user = $this->model('account')->get_user_info_by_uid($dialog['recipient_uid']);
                }

                foreach ($list as $key => $value)
                {
                    $value['message'] = FORMAT::parse_links($value['message']);
                    $value['user_name'] = $recipient_user['user_name'];
                    $value['other_uid'] = $recipient_user['uid'];
                    $value['avatar_file'] = get_avatar_url($value['uid']);
                    $value['add_time'] = date_friendly($value['add_time']);
                    $list_data[] = $value;
                }
            }

            H::ajax_json_output(AWS_APP::RSM($list_data, 1, null));
        }else
        {
            H::ajax_json_output(AWS_APP::RSM([], 1, null));
        }

        
    }

     /*
      获取用户设置
    */
    public function privacy_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $notify_actions = $this->model('notify')->notify_action_details;
        
        ksort($notify_actions);

        $notification_settings = $this->model('account')->get_notification_setting_by_uid($this->user_id);//未选中的设置

        foreach ($notify_actions as $key => $value) {
              
              if($value['user_setting'] == 0)
              {
                  continue;
                  
              }else
              {
                  $value['action'] = $key;
              
                  if(in_array($value['action'], $notification_settings['data']))
                  {    

                       $value['check'] = 0;//未选中

                  }else
                  {
                       $value['check'] = 1;//选中
                  }

                  $notify[] = $value;
              }

        }

        $res = array(
               'inbox_recv' => $this->user_info['inbox_recv'],//私信设置
               'notify_actions' => $notify,
        );
        
        H::ajax_json_output(AWS_APP::RSM($res, 1, null));

    }


    //专栏首页数据
    public function column_index_data_action()
    {
        
        $page = intval($_GET['page']) ? :1;

        $per_page = intval($_GET['per_page']) ? :3;

        $column_info = $this->model('column')->fetch_column_list($this->user_id ? :0 , $page , $per_page , 'sum');

        foreach ($column_info as $key => $value) {

             $column_info[$key]['add_time'] = date_friendly($value['add_time']);

             $column_info[$key]['focus'] = $value['has_focus_column']? :0;

             unset($column_info[$key]['has_focus_column']);

        }

        H::ajax_json_output(AWS_APP::RSM($column_info, 1, null));
    }

    //专栏首页文章列表

    public function column_info_action()
    {   
        if(!intval($_GET['column_id']))
        {
               H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('专栏ID必传')));
        }

        $column_info = $this->model('column')->get_column_by_id(intval($_GET['column_id']));

        $column_info['user_name'] = $this->model('account')->get_user_info_by_uid($column_info['uid'])['user_name'];
        
        if($this->user_id)
        {
           $column_info['focus'] = $this->model('column')->has_focus_column($this->user_id, $column_info['column_id'])?1:0;

        }else
        {
           $column_info['focus'] = 0;
        }

        $column_info['article_count'] = $this->model('article')->count('article','column_id = '.$column_info['column_id']);
        
        H::ajax_json_output(AWS_APP::RSM($column_info, 1, null));
    }

    public function column_index_article_action()
    {   
        $page = intval($_GET['page']) ? :1;

        $per_page = intval($_GET['per_page']) ? :6;

        $column_id = intval($_GET['column_id'])? :0;

        if($_GET['sort'] == 'hot')
        {
           $order = '(comments + views + votes) desc';

        }else
        {
           $order = 'add_time desc';
        }

        $article_list = $this->model('api')->get_articles_list($column_id, $page , $per_page , $order , null, false);

        $data = $this->model('api')->return_index_result($article_list);

        H::ajax_json_output(AWS_APP::RSM($data, 1, null));
    }


    /*
      邀请用户回答(获取用户列表)
    */
    public function question_invite_users_list_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $question_id=intval($_GET['question_id']);

        $user_name=trim($_GET['q']);

        $page=intval($_GET['page']);

        $status=intval($_GET['status']);

        if(!$question_id)
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题ID必传')));

        
        $topics= $this->model('topic')->fetch_all('topic_relation',"is_del = 0 and type = 'question' and item_id = ".$question_id);

        if($topics)
        {
            foreach ($topics as $key => $value) {
                $topic_ids[] = $value['topic_id'];
            }

            $topic_ids = implode(',', $topic_ids)? :'0';

        }else
        {
            $topics=$this->model('topic')->fetch_all('topic_focus','uid='.$this->user_id);
        
            foreach ($_topics as $key => $value) {
                $topic_ids[] = $value['topic_id'];
            }

            $topic_ids = implode(',', $topic_ids)? :'0';
        }

        if($status)
        {
            $ret = $this->model('api')->get_user_answer_number($this->user_id,$topic_ids,null);

        }else
        {
            if($user_name)
            {
                $ret = $this->model('api')->get_user_answer_number($this->user_id,$topic_ids,$user_name);

            }else
            {   
                $ret = $this->model('api')->get_users($this->user_id,$topic_ids,$page);
            }
        }
       
        foreach ($ret as $key => $value) {

            $ret[$key]['avatar']=get_avatar_url($value['uid']);

            $has_question_invite=$this->model('question')->has_question_invite($question_id,$value['uid'],$this->user_id);
            
            $ret[$key]['has_invite']=$has_question_invite?1:0;
        }
        
        H::ajax_json_output(AWS_APP::RSM($ret, 1, null));

    }


    /*
      文章详情(article)
      xin
    */
    public function article_details_action()
    {   
        if (!$article_info = $this->model('article')->get_article_info_by_id(intval($_GET['article_id'])))
        {
           H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('文章不存在')));
        }

        $page = intval($_GET['page'])? :0;

        if($_GET['sort'] == 'hot')
        {
            $order = 'votes DESC';

        }else
        {
            $order = 'add_time DESC';
        }

        if($article_info['is_del'] != 0)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('文章已被删除')));
        }

        $article_info['add_time'] = date_friendly($article_info['add_time']);

        $article_info['user_info'] = $this->model('account')->get_user_info_by_uid($article_info['uid'], false , false , true ,[uid,user_name]);

        $article_info['user_info']['avatar'] = get_avatar_url($article_info['user_info']['uid']);;
 
        $article_info['message'] = replacePicUrl(html_entity_decode($article_info['message']),base_url());

        $article_info['message']=replaceVideoUrl(html_entity_decode($article_info['message']),base_url());
        
        $article_info['article_topics'] = $this->model('topic')->get_topics_by_item_id($article_info['id'], 'article');

        if ($this->user_id)
        {   

            $article_info['user_info']['focus'] = $this->model('follow')->user_follow_check($this->user_id, $article_info['user_info']['uid'])? :0;

             //判断是否收藏
            $article_info['fav'] = $this->model('favorite')->fetch_one('favorite','id',"uid={$this->user_id} and type='article' and item_id=".$article_info['id'])?1:0;

             //判断是否举报
            $article_info['report'] = $this->model('account')->fetch_one('report','id',"uid={$this->user_id} and type='article' and target_id=".$article_info['id']." and status=0")?1:0;

            $article_info['vote_info'] = $this->model('article')->get_article_vote_by_id('article', $article_info['id'], null, $this->user_id)['rating']? :0;


            if($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator'])
            {
                 $article_info['permission'] = 1;//管理员

            }else if($question_info['published_uid'] == $this->user_id)
            {
                 $article_info['permission'] = 2;//发起者

            }else
            {
                 $article_info['permission'] = 3;//普通用户
            }

        }else
        {   
            $article_info['user_info']['focus'] = 0;

            $article_info['fav'] = 0;

            $article_info['report'] = 0;

            $article_info['vote_info'] = 0;

            $article_info['permission'] = 0;
        }
 
        $article_info['comment'] = $this->model('api')->get_article_comments($article_info['id'],5,$page,$order); 
       
        $user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($article_info['comment'], 'uid'));

        $at_user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($article_info['comment'], 'at_uid'));

        foreach ($article_info['comment'] as $key => $value) {
            
            $comment_mes_info = $this->model('api')->parse_at_user($value['message'],false,false,true);

            if($value['at_uid'])
            {
                $atuser = array('user_name'=>$at_user_infos[$value['at_uid']]['user_name'],'uid' => $at_user_infos[$value['at_uid']]['uid']);

            }else
            {
                $atuser = null; 
            }

            $article_info['comment'][$key]['message'] = $comment_mes_info['message'];

            $article_info['comment'][$key]['atuser'] = $atuser;

            $article_info['comment'][$key]['user_name'] = $user_infos[$value['uid']]['user_name'];

            $article_info['comment'][$key]['avatar'] = get_avatar_url($value['uid'],'max');

            $article_info['comment'][$key]['time'] = date_friendly($article_info['comment'][$key]['add_time']);

            unset($article_info['comment'][$key]['at_uid']);

            unset($article_info['comment'][$key]['add_time']);
            
            if($this->user_id)
            {
                  $article_info['comment'][$key]['status'] = $this->model('article')->get_article_vote_by_id('comment', $value['id'], 1, $this->user_id)?1:0;

                   //判断是否举报
                  $article_info['comment'][$key]['report'] = $this->model('account')->fetch_one('report','id',"uid={$this->user_id} and type='article_answer' and target_id=".$value['id']." and status=0")?1:0;

            }else
            {
                  $article_info['comment'][$key]['status'] = 0;

                  $article_info['comment'][$key]['report'] = 0;
            }


        }
        
        $this->model('article')->update_views($article_info['id']);


        H::ajax_json_output(AWS_APP::RSM($article_info, 1, null));

    }
    

    public function get_topics_action()
    {
        $recently_list = null;

        if($this->user_id)
        {
            $user_info = $this->model('account')->get_user_info_by_uid(intval($this->user_id), TRUE);
            if($user_info && $user_info['uid']!=-1){
                $recent_topics = @unserialize($user_info['recent_topics']);
                
                foreach ($recent_topics as $key => $va) {
                    $topic_id = $this->model('topic')->get_topic_id_by_title($va);
                    $recently_list[$key]['topic_id'] = $topic_id;
                    $recently_list[$key]['topic_title'] = $va;
                    $recently_list[$key]['url_token'] = rawurlencode($va);
                }
            }
        }

        $recommend_info = $this->model('topic')->get_topic_list(null,'discuss_count desc,focus_count desc',10);
        
        foreach ($recommend_info as $key => $value) {
                $recommend_list[$key]['topic_id'] = $value['topic_id'];
                $recommend_list[$key]['topic_title'] = $value['topic_title'];
                $recommend_list[$key]['topic_pic'] = get_topic_pic_url('mid', $value['topic_pic']);

                $value['topic_description'] = strip_tags(htmlspecialchars_decode(html_entity_decode(nl2br($value['topic_description']))));
                $value['topic_description'] = str_replace('&nbsp;','',$value['topic_description']);
                $value['topic_description'] = str_replace('&nbsp','',$value['topic_description']);
                $value['topic_description'] = str_replace('\\n','',$value['topic_description']);
                $value['topic_description'] = str_replace('\n','',$value['topic_description']);
                $recommend_list[$key]['topic_description'] = $value['topic_description'];

                $recommend_list[$key]['focus_count'] = $value['focus_count'];
                $recommend_list[$key]['discuss_count'] = $value['discuss_count'];
                $recommend_list[$key]['url_token'] = rawurlencode($value['topic_title']);
                if($this->user_id)
                {
                    $recommend_list[$key]['focus'] = $this->model('topic')->has_focus_topic($this->user_id, $value['topic_id'])? :0;
                }else
                {
                    $recommend_list[$key]['focus'] = 0;
                }
        }
        $list = array(
            'recommend_list' => $recommend_list ? $recommend_list : null,
            'recently_list' => $recently_list ? $recently_list : null,
        );

        H::ajax_json_output(AWS_APP::RSM($list, 1, null));
    }

    /**
     * 查询话题列表
     */
    public function get_topic_list_action()
    {
        $recently_list = null;
        $recommend_info = $this->model('topic')->get_topic_list(null,'discuss_count desc,focus_count desc', 10, $_GET['page']);
        foreach ($recommend_info as $key => $value) {
            $recommend_list[$key]['topic_id'] = $value['topic_id'];
            $recommend_list[$key]['topic_title'] = $value['topic_title'];
            $recommend_list[$key]['topic_pic'] = get_setting('upload_url') . '/topic/'.$value['topic_pic'];

            $value['topic_description'] = strip_tags(htmlspecialchars_decode(html_entity_decode(nl2br($value['topic_description']))));
            $value['topic_description'] = str_replace('&nbsp;','',$value['topic_description']);
            $value['topic_description'] = str_replace('&nbsp','',$value['topic_description']);
            $value['topic_description'] = str_replace('\\n','',$value['topic_description']);
            $value['topic_description'] = str_replace('\n','',$value['topic_description']);
            $recommend_list[$key]['topic_description'] = $value['topic_description'];

            $recommend_list[$key]['focus_count'] = $value['focus_count'];
            $recommend_list[$key]['discuss_count'] = $value['discuss_count'];
            if($this->user_id)
            {
                $recommend_list[$key]['focus'] = $this->model('topic')->has_focus_topic($this->user_id, $value['topic_id'])? :0;
            }else
            {
                $recommend_list[$key]['focus'] = 0;
            }
        }
        $list = array(
            'recommend_list' => $recommend_list ? $recommend_list : null,
        );

        H::ajax_json_output(AWS_APP::RSM($list, 1, null));
    }

    public function get_topic_info_action()
    {   
        if(!intval($_GET['topic_id']))
        {
             H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('话题id不得为空')));
        }

        $topic_info = $this->model('topic')->get_topic_list('topic_id = '.intval($_GET['topic_id']),'discuss_count desc,focus_count desc',10);

        if(empty($topic_info))
        {
             H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('话题不存在')));
        }
        
        $info = [];

        foreach ($topic_info as $key => $value) {
                $info['topic_id'] = $value['topic_id'];
                $info['topic_title'] = $value['topic_title'];
                $info['topic_pic'] = get_topic_pic_url('mid', $value['topic_pic']);

                $value['topic_description'] = strip_tags(htmlspecialchars_decode(html_entity_decode(nl2br($value['topic_description']))));
                $value['topic_description'] = str_replace('&nbsp;','',$value['topic_description']);
                $value['topic_description'] = str_replace('&nbsp','',$value['topic_description']);
                $value['topic_description'] = str_replace('\\n','',$value['topic_description']);
                $value['topic_description'] = str_replace('\n','',$value['topic_description']);

                $info['topic_description'] = $value['topic_description'];
                $info['focus_count'] = $value['focus_count'];
                $info['discuss_count'] = $value['discuss_count'];
                $info['url_token'] = rawurlencode($value['topic_title']);
                if($this->user_id)
                {
                    $info['focus'] = $this->model('topic')->has_focus_topic($this->user_id, $value['topic_id'])? :0;
                }else
                {
                    $info['focus'] = 0;
                }
        }
        
        H::ajax_json_output(AWS_APP::RSM($info, 1, null));

    }

    public function get_category_action()
    {   
        $category_list = array_values($this->model('system')->fetch_category('question',0));

        H::ajax_json_output(AWS_APP::RSM($category_list, 1, null));
    }
    
    
    public function get_nav_action()
    {
        if (get_setting('category_enable') == 'Y')
        {
            $category_list = $this->model('api')->get_nav_menu_list_api(1);

            unset($category_list['category_ids']);

            unset($category_list['base']);

            $category_list = array_values($category_list);
        }

        H::ajax_json_output(AWS_APP::RSM($category_list, 1, null));
    }


    public function get_report_reason_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(mb_strlen(get_setting('report_reason')))
        {
            $report_reason = explode(',', get_setting('report_reason'));

        }else
        {
            $report_reason = [];
        }

        H::ajax_json_output(AWS_APP::RSM($report_reason, 1, null));
    }


    /*
      用户首页
    */
    public function user_action()
    {   

        $uid=intval($_GET['uid']);

        if(!$uid)
          H::ajax_json_output(AWS_APP::API(null, 10001, 'uid不能为空'));

        $user=$this->model('account')->get_user_info_by_uid($uid,true);

        if(!$user)
          H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("该用户已不存在")));

        $user['avatar']=get_avatar_url($user['uid']);
        
        if($this->user_id)
        {
            $user['focus']=$this->model('follow')->user_follow_check($this->user_id,$uid)?1:0;

        }else
        {
            $user['focus']=0;
        }
        

        $signature=$this->model('account')->fetch_one('users_attrib','signature',"uid=$uid");

        $user['group_name']=$this->model('account')->fetch_one('users_group','group_name',"group_id={$user['reputation_group']}");    

        $user['signature']=$signature?$signature:'暂无签名';


        H::ajax_json_output(AWS_APP::RSM($user, 1, null));

    }

    /*
      用户首页
    */
    public function people_action()
    {
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        // $users=$this->model('account')->fetch_all('users','is_del=0','reputation desc,fans_count DESC','','',['user_name','uid','reputation','fans_count']);
        // foreach ($users as $key => $value) {
        //      if($this->user_id==$value['uid']){
        //          $user['ranking']=$key+1;    
        //      }
        // }
        $user['user_name']=$this->user_info['user_name'];
        $user['sex']=$this->user_info['sex'];   
        $user['avatar']=get_avatar_url($this->user_id);    
        $user['real_avatar']=get_setting('upload_url').'/avatar/'.$this->model('account')->get_avatar($this->user_id, null, 0);    
        $user['signature']=$this->user_info['signature'];    
        $user['reputation']=$this->user_info['reputation'];    
        $user['fans_count']=$this->user_info['fans_count'];    
        $user['friend_count']=$this->user_info['friend_count'];    
        $user['integral']=$this->user_info['integral'];    
        $user['verified']=$this->user_info['verified'];    
        $user['group_name']=$this->model('account')->fetch_one('users_group','group_name',"group_id={$this->user_info['reputation_group']}");    
        $user['draft_count']=$this->model('draft')->count('draft',"uid={$this->user_id} and (type='answer' or type='article_answer')");    
        $user['unread_msg']=$this->user_info['notification_unread']+$this->user_info['inbox_unread'];    
        $count=$this->model('favorite')->fetch_all('favorite',"uid={$this->user_id} and (type='article' or type='question')"); 
        $_count=0;
        foreach ($count as $key => $value) {
            switch ($value['type']) {
                case 'article':
                    $_count+=$this->model('article')->count('article',"is_del!=1 and id=".$value['item_id']);
                    break;
                case 'question':
                    $_count+=$this->model('question')->count('question',"is_del!=1 and question_id=".$value['item_id']);
                    break;
            }
        }
        $user['favorite_count']=$_count;
        H::ajax_json_output(AWS_APP::RSM($user, 1, null));


    }


    /*
      用户参与
    */
    public function user_action_history_action()
    {   
        
        if(isset($_GET['uid']))
        {
             $uid = $_GET['uid'];   

        }else if($this->user_id)
        {
             $uid = $this->user_id;

        }else
        {
             H::ajax_json_output(AWS_APP::RSM($list, -1, AWS_APP::lang()->_t('访问用户不得为空')));
        }

        $page = isset($_GET['page']) && intval($_GET['page']) ? intval($_GET['page']) : 1;  
        $type = isset($_GET['type']) && $_GET['type'] ? $_GET['type'] : 'all'; //question问答 article帖子 all全部

        $activity_list = $question_list = $bar_list = null;
        $question_ids=array();
        if($type == 'question' || $type == 'all'){
            //我的问答
            $answer_list = $this->model('api')->get_answers_by_uid($uid,['question_id','answer_content']);
            foreach ($answer_list as $key => $va) {
                $question_ids[] += $va['question_id'];
            }
           
            $where=!empty($question_ids) ?'published_uid='.$uid." or question_id IN(" . implode(',', array_unique($question_ids)) . ")":'published_uid='.$uid.' and is_del = 0';

            $question_list = $this->model('api')->question_data($where,$page,'update_time desc',$this->per_page);
        }

        if($type == 'article' || $type == 'all'){
            //我的帖子
            $where='uid='.$uid.' and is_del = 0';
            $article_list = $this->model('api')->article_data($where,$page,'add_time desc',$this->per_page);

            $article_list = $this->model('api')->return_index_result($article_list);
        }

        $list = array(
            'question_list' => $question_list ? $question_list : [],//我的问答
            'article_list' => $article_list ? $article_list : [],//我的问答
            
        );
        H::ajax_json_output(AWS_APP::RSM($list, 1, null));

    }


    /*
      用户收藏(帖子-问答-活动)
    */
    public function user_favorite_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $uid = $this->user_id;   

        $page = isset($_GET['page']) && intval($_GET['page']) ? intval($_GET['page']) : 1; 

        $type = isset($_GET['type']) && $_GET['type'] ? $_GET['type'] : 'answer'; //question问答 article帖子 

        $question_list = $article_list = null;

        //根据uid 和 类型 取出收藏数据
        $where = 'uid ='.$uid." and type='".$type ."'";
 
        $favorite = $this->model('api')->get_favorite_by_uid($where, 'id desc', $page, 10,  true);
        
        $article_ids=$question_ids=array();

        foreach ($favorite as $key => $va) {
            switch ($va['type']) {
                case 'answer':
                    $question_ids[] += $va['item_id'];
                    break;
                case 'article':
                    $article_ids[] += $va['item_id'];
                    break;
            }
        }

        if($type == 'answer' && $question_ids){
            
            $question_list = $this->model('api')->favitor_data($uid,$page-1);
            
        }

        if($type == 'article'  && $article_ids){

            //帖子
            $where = "id IN(" . implode(',', array_unique($article_ids)) . ") and is_del = 0";

            $article_list = $this->model('api')->article_data($where,$page,'add_time desc',$this->per_page);

            $article_list = $this->model('api')->return_index_result($article_list);
        }
        

        $list = array(
            'question_list' => $question_list ? $question_list : [],//我的问答
            'article_list' => $article_list ? $article_list : [],//我的帖子
        );
        H::ajax_json_output(AWS_APP::RSM($list, 1, null));

    }


    /*
      用户草稿(帖子-问答-回复)
    */
    public function user_draft_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $uid = $this->user_id;

        $page = isset($_GET['page']) && intval($_GET['page']) ? intval($_GET['page']) : 1;  

        $type = isset($_GET['type']) && $_GET['type'] ? $_GET['type'] : 'all'; //answer问答 article文章 question 问题 all全部
        
        $activity_list = $question_list = null;
        //根据uid 和 类型 取出草稿数据
        $where = $type != 'all' ? 'uid ='.$uid." and type='".$type ."'": 'uid ='.$uid;

        $draft = $this->model('api')->get_draft_page($where, 'id DESC', 10, $page);

        foreach ($draft as $key => $value) {
              $draft[$key]['time'] = date_friendly($value['time']);
        }
  
        H::ajax_json_output(AWS_APP::RSM($draft, 1, null));

    }

    /*
      获取用户草稿(帖子-问答-回复)
    */
    public function get_user_one_draft_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $uid = $this->user_id;

        $type = $_GET['type']; //answer问答 article文章 question 问题

        $item_id = intval($_GET['item_id'])? :0;
        
        if($type == 'answer' && !$item_id)
        {
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('该类型需选择关联id')));
        }
        //根据uid 和 类型 取出草稿数据

        $draft = $this->model('draft')->get_draft($item_id,$type,$uid);

        foreach ($draft as $key => $value) {
              $draft[$key]['time'] = date_friendly($value['time']);
        }
  
        H::ajax_json_output(AWS_APP::RSM($draft, 1, null));

    }


    /*
      用户粉丝
    */
    public function user_fans_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $users_ids=array();

        $page = isset($_GET['page']) && intval($_GET['page']) ? intval($_GET['page']) : 1;

        $per_page=isset($_GET['per_page']) && intval($_GET['per_page']) ? intval($_GET['per_page']) : $this->per_page;

        $users_list = $this->model('api')->get_user_fans_by_uid($this->user_id,$page,$per_page);
          
        if ($users_list AND $this->user_id)
        {
            foreach ($users_list as $key => $val)
            {
                $users_ids[] = $val['uid'];
            }

            if ($users_ids)
            {
                foreach ($users_list as $key => $val)
                {
                    $reult_list[$key]['uid'] = $val['uid'];
                    $reult_list[$key]['user_name'] = $val['user_name'];
                    $reult_list[$key]['avatar_file'] = get_avatar_url($val['uid'], 'mid');
                    $reult_list[$key]['fans_count'] = $val['fans_count'];//关注
                    $reult_list[$key]['focus']=$this->model('follow')->user_follow_check($this->user_id,$val['uid']) ? 1 : 0 ;
                }
            }
        }

        array_multisort(array_column($reult_list,'uid'),SORT_DESC,$reult_list);

        H::ajax_json_output(AWS_APP::RSM($reult_list, 1, null));

    }


     /*
      用户关注(问题-用户-专栏)
    */
    public function user_follow_action()
    {
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $uid = $this->user_id;   
        $page = isset($_GET['page']) && intval($_GET['page']) ? intval($_GET['page']) : 1;  
        $type = isset($_GET['type']) && $_GET['type'] ? $_GET['type'] : 'all'; //question问答 article文章 user用户 all全部

        $user_list = $question_list = $bar_list = null;

        if($type == 'question' || $type == 'all'){
            //我的问答
            $focus = $this->model('api')->get_focus_question_page_by_uid($uid,'focus_id DESC',$this->per_page,$page);

            foreach ($focus as $key => $va) {
                $question_ids[] += $va['question_id'];
            }

            //对数据重新组装
            //$question_list = $this->question_info_list($questions);
            if(!empty($question_ids))
            {
                $question_list = $this->model('api')->question_data("question_id IN(" . implode(',', array_unique($question_ids)) . ")",1);

            }else{

                $question_list = [];

            }

        }

        if($type == 'column' || $type == 'all'){
            
            $focus = $this->model('api')->get_focus_column_page_by_uid($uid,'focus_id DESC',$this->per_page,$page);
            
            

            if($focus)
            {
                foreach ($focus as $key => $va) {
                    $column_ids[] += $va['column_id'];
                }

                $column_list = $this->model('api')->fetch_all('column',"column_id IN(" . implode(',', array_unique($column_ids)) . ")",'column_id desc');

                foreach ($column_list as $key => $value) {
                    
                    if($this->user_id)
                    {
                        $column_list[$key]["focus"] = $this->model('column')->has_focus_column($this->user_id, $value['column_id'])?1:0;
                    }else
                    {
                        $column_list[$key]["focus"] = 0;
                    }

                    $column_list[$key]['article_count'] = $this->model('article')->count('article','column_id = '.$value['column_id']);
                }

            }else
            {
                $column_list = [];
            }
        }

        if($type == 'user' || $type == 'all'){
            
            $follow = $this->model('api')->get_focus_users_page_by_uid($uid,'follow_id DESC',$this->per_page,$page);

            if($follow){

                foreach ($follow as $key => $val){
                    $friend_uids[] += $val['friend_uid'];
                }

                $user_infos = $this->model('account')->get_user_info_by_uids(array_unique($friend_uids), TRUE,false);
                foreach ($user_infos as $key => $va) {
                    $user_infos_resul[$va['uid']] = $va;
                }

                $i = 0;
                foreach ($follow as $key => $va) {
                    $user_list[$i]['uid'] = $va['friend_uid'];
                    $user_list[$i]['user_name'] = $user_infos_resul[$va['friend_uid']]['user_name'];
                    $user_list[$i]['avatar_file'] = get_avatar_url($user_infos_resul[$va['friend_uid']]['uid'], 'mid');
                    $user_list[$i]['friend_count'] = $user_infos_resul[$va['friend_uid']]['friend_count'];
                    $user_list[$i]['fans_count'] = $user_infos_resul[$va['friend_uid']]['fans_count'];
                    if($this->user_id)
                    {
                        $user_list[$i]['focus']=$this->model('follow')->user_follow_check($this->user_id,$va['ufriend_uidid'])? :0;
                    }else
                    {
                        $user_list[$i]['focus']=0;
                    }
                    
                    $i++;
                }

            }else
            {
                $user_list = [];
            }
        }

        $list = array(
            'question_list' => $question_list  ? $question_list : [],//我的关注问答
            'column_list' => $column_list  ? $column_list : [],//我的关注专栏
            'user_list' => $user_list  ? $user_list : [],//我的关注用户
        );

        H::ajax_json_output(AWS_APP::RSM($list, 1, null));

    }


 //    //个人中心
    // public function people_action()
    // {
    //  if (isset($_GET['notification_id']))
    //  {
    //      $this->model('notify')->read_notification($_GET['notification_id'], $this->user_id);
    //  }

    //  if ($this->user_id AND !$_GET['id'])
    //  {
    //      $user = $this->user_info;
    //  }
    //  else
    //  {
    //      if (is_digits($_GET['id']))
    //      {
    //          if (!$user = $this->model('account')->get_user_info_by_uid($_GET['id'], TRUE))
    //          {
    //              $user = $this->model('account')->get_user_info_by_username($_GET['id'], TRUE);
    //          }
    //      }
    //      else if ($user = $this->model('account')->get_user_info_by_username($_GET['id'], TRUE))
    //      {

    //      }
    //      else
    //      {
    //          $user = $this->model('account')->get_user_info_by_url_token($_GET['id'], TRUE);
    //      }

    //      if (!$user)
    //      {
    //          H::ajax_json_output(AWS_APP::RSM(null, '-1', '用户不存在'));
    //      }

    //      $this->model('people')->update_views($user['uid']);
    //  }

    //  if ($user['forbidden'] AND !$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'])
    //  {
    //      H::ajax_json_output(AWS_APP::RSM(null, '-1', '该用户已被封禁'));
    //  }

    //  $user['user_follow_check'] = $this->model('follow')->user_follow_check($this->user_id, $user['uid']);

    //  $user['fans_list'] = array_values($this->model('follow')->get_user_fans($user['uid'], 20))? :array();

    //  $user['friends_list'] = array_values($this->model('follow')->get_user_friends($user['uid'], 20))? :array();

 //        $user['focus_topics'] = array_values($this->model('topic')->get_focus_topic_list($user['uid'], 8))? :array();

 //        $user['user_actions_questions'] = $this->model('actions')->get_user_actions($user['uid'], get_setting('contents_per_page'), ACTION_LOG::ADD_QUESTION, $this->user_id);

 //        $user['user_actions_answers'] = $this->model('actions')->get_user_actions($user['uid'], get_setting('contents_per_page'), ACTION_LOG::ANSWER_QUESTION, $this->user_id);

 //        $user['user_actions_questions_count'] = count($this->model('actions')->get_user_actions($user['uid'], null, ACTION_LOG::ADD_QUESTION, $this->user_id));

 //        $user['user_actions_answers_count'] = count($this->model('actions')->get_user_actions($user['uid'], null, ACTION_LOG::ANSWER_QUESTION, $this->user_id));

 //        $user['job_info'] = $this->model('account')->get_jobs_by_id($user['job_id'])['job_name']? :'';
        
 //        $user['sina_weibo_enabled'] = get_setting('sina_weibo_enabled');

 //        H::ajax_json_output(AWS_APP::RSM($user, 1, null));
    // }

    public function login_action()
    {
        if ($this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('已登录')));
        }

        if (get_setting('sina_weibo_enabled') == 'Y')$result['weibo'] = 1;
          $result['weibo'] = 0;

        if(get_setting('qq_login_enabled') == 'Y')$result['qq'] = 1;
          $result['qq'] = 1;

        H::ajax_json_output(AWS_APP::RSM($result, 1, null));

    }

   
    public function register_action()
    {   
        if ($this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('已登录')));
        }

        $type = 'email';

        if(get_hook_info('mobile_regist')['state'] == 1){

            switch (get_hook_config('mobile_regist')['register_valid_type']['value'])
            {
                case 'mobile':
                    $type = 'mobile';
                    break;

                case 'double_certification':
                    $type = 'double';
                    break;
            }
        }

        $result['type'] = $type;

        H::ajax_json_output(AWS_APP::RSM($result, 1, null));
        
    }


    //问题详情
    public function question_action()
    {

        if (!$this->user_id AND !$this->user_info['permission']['visit_question'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请先登陆')));
        }

        if (! isset($_GET['id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题id不得为空')));
        }

        if ($_GET['notification_id'])
        {
            $this->model('notify')->read_notification($_GET['notification_id'], $this->user_id);
        }

        if (! $question_info = $this->model('question')->get_question_info_by_id($_GET['id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题不存在')));
        }

        $question_info['user_info'] = $this->model('account')->get_user_info_by_uid($question_info['published_uid']);

        $question_info['redirect'] = $this->model('question')->get_redirect($question_info['question_id']);

        if ($question_info['redirect']['target_id'])
        {
            $target_question = $this->model('question')->get_question_info_by_id($question_info['redirect']['target_id']);
        }

        if (is_digits($_GET['rf']) and $_GET['rf'])
        {
            if ($from_question = $this->model('question')->get_question_info_by_id($_GET['rf']))
            {
                $redirect_message[] = AWS_APP::lang()->_t('从问题') . $from_question['question_content'] . AWS_APP::lang()->_t('跳转而来');
            }
        }

        if ($question_info['redirect'] and ! $_GET['rf'])
        {
            if ($target_question)
            {
                HTTP::redirect('/m/question/' . $question_info['redirect']['target_id'] . '?rf=' . $question_info['question_id']);
                
                H::ajax_json_output(AWS_APP::RSM(array(
                    'question_id'=>$question_info['redirect']['target_id'],
                    'rf'=>$question_info['question_id']
                    ), -1, AWS_APP::lang()->_t('问题重定向')));
            }
            else
            {
                $redirect_message[] = AWS_APP::lang()->_t('重定向目标问题已被删除, 将不再重定向问题');
            }
        }
        else if ($question_info['redirect'])
        {
            if ($target_question)
            {
                $message = AWS_APP::lang()->_t('此问题将跳转至') . $target_question['question_content'];

                if ($this->user_id AND ($this->user_info['permission']['is_administortar'] OR $this->user_info['permission']['is_moderator'] OR (!$this->question_info['lock'] AND $this->user_info['permission']['redirect_question'])))
                {
                    $message .= AWS_APP::lang()->_t('撤消重定向');
                }

                $redirect_message[] = $message;
            }
            else
            {
                $redirect_message[] = AWS_APP::lang()->_t('重定向目标问题已被删除, 将不再重定向问题');
            }
        }

        if ($question_info['has_attach'])
        {
            $question_info['attachs'] = $this->model('publish')->get_attach('question', $question_info['question_id'], 'min');
            foreach ($question_info['attachs'] as $key=>$val){
                if(strpos($question_info['question_detail'],$val['file_location']) !== false){
                    $question_info['attachs_ids'][] = $val['id'];
                }
            }
        }

        $this->model('question')->update_views($question_info['question_id']);

        if (get_setting('answer_unique') == 'Y')
        {
            if ($this->model('answer')->has_answer_by_uid($question_info['question_id'], $this->user_id))
            {   
                $question_info['user_answered'] = 1;
            }
        }

        $question_info['question_detail'] = html_entity_decode(FORMAT::parse_attachs(nl2br(FORMAT::parse_bbcode($question_info['question_detail']))));
        
        $question_info['question_focus'] = $this->model('question')->has_focus_question($question_info['question_id'], $this->user_id);

        $question_info['question_topics'] = $this->model('topic')->get_topics_by_item_id($question_info['question_id'], 'question');

        $question_info['redirect_message'] = $redirect_message;

        if ($this->user_id)
        {   
            $question_info['question_thanks'] = $this->model('question')->get_question_thanks($question_info['question_id'], $this->user_id)?1:0;

            $question_info['invite_users'] = $this->model('question')->get_invite_users($question_info['question_id']);
            
            if ($this->user_info['draft_count'] > 0)
            {   
                $question_info['draft_content'] = $this->model('draft')->get_data($question_info['question_id'], 'answer', $this->user_id);

            }
        }


        
        $question_info['attach_access_key'] = md5($this->user_id . time());

        $question_info['post_hash'] = new_post_hash();
        
        $question_info['human_valid'] = human_valid('answer_valid_hour');

        $question_info['add_time'] = date_friendly($question_info['add_time']);

        $question_info['update_time'] = date_friendly($question_info['update_time']);

        // $question_info['question_related_list'] = $this->model('question')->get_related_question_list($question_info['question_id'], $question_info['question_content']);

        // $question_info['question_related_links'] = $this->model('related')->get_related_links('question', $question_info['question_id']);
    
        if ($this->user_id)
        {
            if ($question_topics)
            {
                foreach ($question_topics AS $key => $val)
                {
                    $question_topic_ids[] = $val['topic_id'];
                }
            }

            if ($helpful_users = $this->model('topic')->get_helpful_users_by_topic_ids($question_topic_ids, 12))
            {
                foreach ($helpful_users AS $key => $val)
                {
                    if ($val['user_info']['uid'] == $this->user_id)
                    {
                        unset($helpful_users[$key]);
                    }
                    else
                    {
                        $helpful_users[$key]['has_invite'] = $this->model('question')->has_question_invite($question_info['question_id'], $val['user_info']['uid'], $this->user_id);
                    }
                }

                $question_info['helpful_users'] = $helpful_users;
            }
        }

        H::ajax_json_output(AWS_APP::RSM($question_info, 1, null));

    }
    public function get_question_answer_list_action()
    {   

        if (! $question_info = $this->model('question')->get_question_info_by_id($_GET['id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题不存在')));
        }

        $answer_list = $this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], calc_page_limit($_GET['page'], 20), null, 'agree_count DESC, against_count ASC, add_time ASC');

        // 最佳回复预留
        $answers[0] = '';

        if (! is_array($answer_list))
        {
            $answer_list = array();
        }

        $answer_ids = array();
        $answer_uids = array();

        foreach ($answer_list as $answer)
        {
            $answer_ids[] = $answer['answer_id'];
            $answer_uids[] = $answer['uid'];

            if ($answer['has_attach'])
            {
                $has_attach_answer_ids[] = $answer['answer_id'];
            }
        }

        if (!in_array($question_info['best_answer'], $answer_ids) AND intval($_GET['page']) < 2)
        {
            $answer_list = array_merge($this->model('answer')->get_answer_list_by_question_id($question_info['question_id'], 1, 'answer_id = ' . $question_info['best_answer']), $answer_list);
        }

        if ($answer_ids)
        {
            $answer_agree_users = $this->model('answer')->get_vote_user_by_answer_ids($answer_ids);

            $answer_vote_status = $this->model('answer')->get_answer_vote_status($answer_ids, $this->user_id);

            $answer_users_rated_thanks = $this->model('answer')->users_rated('thanks', $answer_ids, $this->user_id);
            $answer_users_rated_uninterested = $this->model('answer')->users_rated('uninterested', $answer_ids, $this->user_id);
            $answer_attachs = $this->model('publish')->get_attachs('answer', $has_attach_answer_ids, 'min');
        }

        foreach ($answer_list as $answer)
        {
            if ($answer['has_attach'])
            {
                $answer['attachs'] = $answer_attachs[$answer['answer_id']];

                $answer['insert_attach_ids'] = FORMAT::parse_attachs($answer['answer_content'], true);
            }

            $answer['user_rated_thanks'] = $answer_users_rated_thanks[$answer['answer_id']];
            $answer['user_rated_uninterested'] = $answer_users_rated_uninterested[$answer['answer_id']];

            $answer['answer_content'] = $this->model('question')->parse_at_user(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($answer['answer_content']))));

            $answer['agree_users'] = $answer_agree_users[$answer['answer_id']];
            $answer['agree_status'] = $answer_vote_status[$answer['answer_id']];

            if ($question_info['best_answer'] == $answer['answer_id'] AND intval($_GET['page']) < 2)
            {
                $answers[0] = $answer;
            }
            else
            {
                $answers[] = $answer;
            }
        }

        if (! $answers[0])
        {
            unset($answers[0]);
        }

        if (get_setting('answer_unique') == 'Y')
        {
            if ($this->model('answer')->has_answer_by_uid($question_info['question_id'], $this->user_id))
            {
                $question_info['user_answered'] = 1;
            }
        }
        
        H::ajax_json_output(AWS_APP::RSM(array_values($answers), 1, null));

    }


    public function article_action()
    {
        if ($_GET['notification_id'])
        {
            $this->model('notify')->read_notification($_GET['notification_id'], $this->user_id);
        }

        if (! $article_info = $this->model('article')->get_article_info_by_id($_GET['id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('文章不存在')));
        }

        if ($article_info['has_attach'])
        {
            $article_info['attachs'] = $this->model('publish')->get_attach('article', $article_info['id'], 'min');

            //检测出在文章内容中已经出现的附件，前台渲染时，只渲染未在内容中出现的附件
            foreach ($article_info['attachs'] as $key=>$val){
                if(strpos($article_info['message'],$val['file_location']) !== false){
                    $article_info['attachs_ids'][] = $val['id'];
                }
            }
//          $article_info['attachs_ids'] = FORMAT::parse_attachs($article_info['message'], true);
        }

        $article_info['user_info'] = $this->model('account')->get_user_info_by_uid($article_info['uid'], true);

        $article_info['message'] = html_entity_decode(FORMAT::parse_attachs(nl2br(FORMAT::parse_bbcode($article_info['message']))));

        if ($this->user_id)
        {
            $article_info['vote_info'] = $this->model('article')->get_article_vote_by_id('article', $article_info['id'], null, $this->user_id);
        }

        $article_info['vote_users'] = $this->model('article')->get_article_vote_users_by_id('article', $article_info['id'], 1, 10);

        $article_info['article_topics'] = $this->model('topic')->get_topics_by_item_id($article_info['id'], 'article');

        $article_info['is_favorite'] = $this->model('favorite')->check_favorite($article_info['id'],'article', $this->user_id);

        $article_info['comments'] = $this->model('article')->get_comments($article_info['id'], $_GET['page'], 100);

        if ($article_info['comments'] AND $this->user_id)
        {
            foreach ($article_info['comments'] AS $key => $val)
            {
                $article_info['comments'][$key]['vote_info'] = $this->model('article')->get_article_vote_by_id('comment', $val['id'], 1, $this->user_id);
            }
        }

        $article_info['attach_access_key'] = md5($this->user_id . time());

        $this->model('article')->update_views($article_info['id']);
        
        $article_info['human_valid'] = human_valid('answer_valid_hour');

        $article_info['post_hash'] = new_post_hash();

        $article_info['add_time'] = date_friendly($article_info['add_time']);
        
        H::ajax_json_output(AWS_APP::RSM($article_info, 1, null));
    }


    
}
