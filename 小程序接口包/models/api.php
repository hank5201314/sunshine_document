<?php

if (!defined('IN_ANWSION')) {
    die;
}

class api_class extends AWS_MODEL
{       
        /*
         *api存储cookie
        */
        public function setcookie_login($uid, $user_name, $password, $salt, $expire = null, $hash_password = true)
        {   
            if (! $uid)
            {
                return false;
            }

            if (! $expire)
            {   
                $key = get_login_cookie_hash($user_name, $password, $salt, $uid, $hash_password);
                HTTP::set_cookie('_user_login', $key , null, '/', null, false, true);
            }
            else
            {   
                $key = get_login_cookie_hash($user_name, $password, $salt, $uid, $hash_password);
                HTTP::set_cookie('_user_login', $key, (time() + $expire), '/', null, false, true);
            }

            return $key;
        }


        /*
         *api获取首页菜单
        */
        public function get_nav_menu_list_api($app = null)
        {
            if (!$nav_menu_data = AWS_APP::cache()->get('nav_menu_list'))
            {
                $nav_menu_data = $this->fetch_all('nav_menu', 'type<>"custom"', 'sort ASC');

                AWS_APP::cache()->set('nav_menu_list', $nav_menu_data, get_setting('cache_level_low'), 'nav_menu');
            }

            if ($nav_menu_data)
            {
                $category_info = $this->model('system')->get_category_list('question');

                switch ($app)
                {
                    case 'explore':
                        $url_prefix = 'explore/';

                        $url_mobile_prefix = 'm/';

                        break;

                    case 'question':
                        $url_prefix = 'question/';

                        $url_mobile_prefix = 'm/';

                        break;

                    case 'article':
                        $url_prefix = 'article/';

                        $url_mobile_prefix = 'm/article/';

                        break;

                    case 'project':
                        $url_prefix = 'project/';

                        $url_mobile_prefix = 'project/';

                        break;
                }

                foreach ($nav_menu_data as $key => $val)
                {
                    switch ($val['type'])
                    {
                        case 'category':
                            if (defined('IN_MOBILE'))
                            {
                                $nav_menu_data[$key]['link'] = $url_mobile_prefix . 'category-' . $category_info[$val['type_id']]['id'];
                            }
                            else
                            {
                                $nav_menu_data[$key]['link'] = $url_prefix . 'category-' . $category_info[$val['type_id']]['url_token'];

                                $nav_menu_data[$key]['child'] = $this->process_child_menu_links($this->model('system')->fetch_category($category_info[$val['type_id']]['type'], $val['type_id']), $app);
                            }
                        break;
                    }
                    
                }

            }

            return $nav_menu_data;
        }

        /*
        获取关注贴吧id集合
        */
        public function get_focus_column_ids_by_uid($uid){
            if (!$user_column = $this->fetch_all('column_focus', 'uid = ' . intval($uid)))
            {
                return false;
            }

            foreach ($user_column AS $key => $val)
            {
                $column_ids[$val['column_id']] = $val['column_id'];
            }

            return $column_ids;
        }


        /*
        根据专栏id集合获取文章
        */
        public function get_article_by_bids($cids,$page,$uid,$limit = 10){

            if($cids){

                $where1 = " where is_del = 0 and uid != $uid and column_id in (".implode(',', $cids).")";
                
            }else
            {
                return false;
            }

            $sql="select * from (select title,message as content,votes,comments as answer_count,add_time as update_time,id AS itemId,1 as type,uid,0 as fid,article_img from ".$this->get_table('article').$where1? :'';
            
            $sql .= ' ) as a order by update_time desc limit '.calc_page_limit($page,$limit);

            $ret = $this->query_all($sql);

            foreach ($ret as $key => $value) {
                   $uids[] = $value['uid'];
            }

            $users_info = $this->model('account')->get_user_info_by_uids($uids,true);
            
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';

            foreach ($ret as $key => $value) {
                   
                   if($value['type'] == 1) //文章
                   {
                        $ret[$key]['message'] = '发表了文章';

                        $ret[$key]['article_img'] = $value['article_img'];

                   }

                   $ret[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['content']))))), 0, 100, 'UTF-8', '...');

                   $ret[$key]['content'] = str_replace('&nbsp;','',$ret[$key]['content']);

                   $ret[$key]['content'] = str_replace('&nbsp','',$ret[$key]['content']);


                   preg_match_all($pattern,htmlspecialchars_decode($value['centent']),$match);

                   $ret[$key]['img'] = $match[src][0];
                  
                   if($ret[$key]['img'])
                   {
                        $ret[$key]['img'] = base_url().$ret[$key]['img'];
                   }

                   $ret[$key]['user_name'] = $users_info[$value['uid']]['user_name'];

                   $ret[$key]['avatar_file'] = get_avatar_url($value['uid'],'max');

                   $ret[$key]['update_time'] = date_friendly($value['update_time']);

                   $ret[$key]['title'] = cjk_substr($value['title'], 0, 30, 'UTF-8', '...');

            }

            return $ret;

        }


        /*
        联合查询获取关注的专栏中的贴子
        */
        public function get_ties_by_ids($cids,$fids,$page,$uid,$limit = 10){

            $cids = $cids ? $cids : [0];

            $fids = $fids ? $fids : [0];

            $sql="select * from (select title,message as content,votes,comments as comments,add_time as update_time,id as itemId,1 as type,uid,column_id as fid,article_img from ".$this->get_table('article')." where is_del != 1 and column_id in (".implode(',', $cids).")";

            $sql.=" union select title,message as content,votes,comments as comments,add_time as update_time,id as itemId,1 as type,uid,column_id as fid,article_img from ".$this->get_table('article')." where is_del != 1 and uid in (".implode(',', $fids).")";

            $sql.=" union all select question_content as title,question_detail as content,agree_count as votes,answer_count as comments,update_time,question_id as itemId,2 as type,published_uid as uid,0 as fid,0 as article_img from ".$this->get_table('question')." where  is_del!=1 and published_uid in (".implode(',', $fids).")";

            $sql.=" union all select 0 as title,answer_content as content,agree_count as votes,comment_count as comments,add_time as update_time,answer_id as itemId,3 as type,uid,question_id as fid,0 as article_img from ".$this->get_table('answer')." where is_del = 0 and uid in(".implode(',', $fids).")";

            $sql .= " ) as a where uid != $uid order by a.update_time desc limit ".calc_page_limit($page,$limit);

            $ret = $this->query_all($sql);

            foreach ($ret as $key => $value) {
                   $uids[] = $value['uid'];
            }

            $users_info = $this->model('account')->get_user_info_by_uids($uids,true);
            
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';

            foreach ($ret as $key => $value) {
                   
                   $ret[$key]['title'] = cjk_substr($value['title'], 0, 30, 'UTF-8', '...');

                   if($value['type'] == 1) //文章
                   {
                        $ret[$key]['message'] = '发表了文章';

                        $ret[$key]['article_img'] = $value['article_img'];

                   }else if($value['type'] == 2)//问题
                   {
                        $ret[$key]['message'] = '发起了问题';

                        $ret[$key]['article_img'] = '';

                   }else if($value['type'] == 3)//回复
                   {
                        $ret[$key]['message'] = '回答了问题';
                        
                        $title = $this->fetch_one('question','question_content','is_del = 0 and question_id = '.$value['fid'])? :'问题已被删除';

                        $ret[$key]['title'] = cjk_substr($title, 0, 30, 'UTF-8', '...');;

                        $ret[$key]['article_img'] = '';
                   }

                   $ret[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['content']))))), 0, 100, 'UTF-8', '...');

                   $ret[$key]['content'] = str_replace('&nbsp;','',$ret[$key]['content']);

                   $ret[$key]['content'] = str_replace('&nbsp','',$ret[$key]['content']);

                   
                   preg_match_all($pattern,htmlspecialchars_decode($value['content']),$match);

                   $ret[$key]['img'] = $match[src][0];
                  
                   if($ret[$key]['img'])
                   {
                        $ret[$key]['img'] = base_url().$ret[$key]['img'];
                   }

                   $ret[$key]['user_name'] = $users_info[$value['uid']]['user_name'];

                   $ret[$key]['avatar_file'] = get_avatar_url($value['uid'],'max');

                   $ret[$key]['update_time'] = date_friendly($value['update_time']);

            }

            return $ret;
        }

        /*
        获取最新的|| 关注的人发布的 问答和帖子
        */
        public function get_newst($page = 1, $uids=[], $uid, $limit = 10){
            
            if($uids){

                $where1=" where is_del = 0 and uid in(".implode(',', $uids).") and uid != $uid";
                $where2=" where is_del = 0 and published_uid in(".implode(',', $uids).") and published_uid != $uid";
                $where3=" where is_del = 0 and uid in(".implode(',', $uids).") and uid != $uid";

                $users_info = $this->model('account')->get_user_info_by_uids($uids,true);

            }else
            {
                return false;
            }

            $sql="select * from (select title,message as content,votes,comments,add_time as update_time,id as itemId,1 as type,uid,column_id as fid,article_img from ".$this->get_table('article').$where1? :'';
            $sql.=" union all select question_content as title,question_detail as content,agree_count as votes,answer_count as comments,update_time,question_id as itemId,2 as type,published_uid as uid,0 as fid,0 as article_img from ".$this->get_table('question').$where2? :'';
            $sql.=" union all select 0 as title,answer_content as content,agree_count as votes,comment_count as comments,add_time as update_time,answer_id as itemId,3 as type,uid,question_id as fid,0 as article_img from ".$this->get_table('answer').$where3? :'';

            $sql .= ' ) as a order by a.update_time desc limit '.calc_page_limit($page,$limit);

            $ret = $this->query_all($sql);
            
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';

            foreach ($ret as $key => $value) {
                   
                   $ret[$key]['title'] = cjk_substr($value['title'], 0, 30, 'UTF-8', '...');

                   if($value['type'] == 1) //文章
                   {
                        $ret[$key]['message'] = '发表了文章';

                        $ret[$key]['article_img'] = $value['article_img'];

                   }else if($value['type'] == 2)//问题
                   {
                        $ret[$key]['message'] = '发起了问题';

                         $ret[$key]['article_img'] = '';

                   }else if($value['type'] == 3)//回复
                   {
                        $ret[$key]['message'] = '回答了问题';
                        
                        $title = $this->fetch_one('question','question_content','is_del = 0 and question_id = '.$value['fid'])? :'问题已被删除';

                        $ret[$key]['title'] = cjk_substr($title, 0, 30, 'UTF-8', '...');

                         $ret[$key]['article_img'] = '';
                   }

                   $ret[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['content']))))), 0, 100, 'UTF-8', '...');

                   $ret[$key]['content'] = str_replace('&nbsp;','',$ret[$key]['content']);

                   $ret[$key]['content'] = str_replace('&nbsp','',$ret[$key]['content']);

                   preg_match_all($pattern,htmlspecialchars_decode($value['content']),$match);

                   $ret[$key]['img'] = $match[src][0];
                  
                   if($ret[$key]['img'])
                   {
                        $ret[$key]['img'] = base_url().$ret[$key]['img'];
                   }

                   $ret[$key]['user_name'] = $users_info[$value['uid']]['user_name'];

                   $ret[$key]['avatar_file'] = get_avatar_url($value['uid'],'max');

                   $ret[$key]['update_time'] = date_friendly($value['update_time']);

            }

            return $ret;

        }

        public function return_index_result($posts_list)
        {   
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';

            $data = [];

            foreach ($posts_list as $key => $value) {

                      if($value['question_id'])
                      {    
                           $data[$key]['post_type'] = 'question';
                           $data[$key]['title'] = cjk_substr($value['question_content'], 0, 30, 'UTF-8', '...');
                           
                           $data[$key]['add_time'] = date_friendly($value['add_time']);
                           $data[$key]['update_time'] = date_friendly($value['update_time']);
                           
                           $data[$key]['comments'] = $value['answer_count'];
                           $data[$key]['views'] = $value['view_count'];
                           $data[$key]['votes'] = $value['agree_count'];
                           $data[$key]['category_id'] = $value['category_id'];
                           
                           
                           if($value['answer_info'])
                           {   
                                
                               $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['answer_info']['answer_content']))))), 0, 100, 'UTF-8', '...');

                               $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                               $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);


                               preg_match_all($pattern,htmlspecialchars_decode($value['answer_info']['answer_content']),$match);

                               $data[$key]['img'] = $match[src][0];
                              
                               if($data[$key]['img'])
                               {
                                    $data[$key]['img'] = base_url().$data[$key]['img'];
                               }

                               $data[$key]['uid'] = $value['answer_info']['uid'];
                              
                               $user_infos = $this->model('account')->get_user_info_by_uid($value['answer_info']['uid']);

                               $data[$key]['user_name'] = $user_infos['user_name'];

                               $data[$key]['avatar_file'] = get_avatar_url($value['answer_info']['uid'],'max');

                               $data[$key]['itemId'] = $value['answer_info']['answer_id'];
                               $data[$key]['message'] = '回复了问题';
                               $data[$key]['anonymous'] = $value['answer_info']['anonymous'];
                               $data[$key]['fid'] = $value['question_id'];
                               $data[$key]['type'] = 3;

                           }else
                           {   
                               
                               $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['question_detail']))))), 0, 100, 'UTF-8', '...');

                               $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                               $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);


                               preg_match_all($pattern,htmlspecialchars_decode($value['question_detail']),$match);

                               $data[$key]['img'] = $match[src][0];
                              
                               if($data[$key]['img'])
                               {
                                    $data[$key]['img'] = base_url().$data[$key]['img'];
                               }

                               if($value['uid'])
                               {  
                                 
                                   $data[$key]['uid'] = $value['uid'];
                               }else
                               {
                                   $data[$key]['uid'] = $value['uid'] = $this->fetch_one('question','published_uid','question_id = '.$value['question_id']);
                               }

                               $user_infos = $this->model('account')->get_user_info_by_uid($value['uid']);
                               
                               $data[$key]['user_name'] = $user_infos['user_name'];

                               $data[$key]['avatar_file'] = get_avatar_url($value['uid'],'max');

                               $data[$key]['itemId'] = $value['question_id'];

                               $data[$key]['message'] = '发起了问题';
                               $data[$key]['anonymous'] = $value['anonymous'];
                               $data[$key]['fid'] = 0;
                               $data[$key]['type'] = 2;
                           }

                           $data[$key]['article_img'] = '';



                           // $data[$key]['comment'] = $this->model('answer')->get_answer_list_by_question_id($value['question_id'], 3);

                  }else
                  {    
                       $data[$key]['post_type'] = 'article';
                       $data[$key]['title'] = cjk_substr($value['title'], 0, 30, 'UTF-8', '...');
                       $data[$key]['itemId'] = $value['id'];
                       $data[$key]['add_time'] = date_friendly($value['add_time']);
                       $data[$key]['uid'] = $value['uid'];
                       $data[$key]['comments'] = $value['comments'];
                       $data[$key]['views'] = $value['views'];
                       $data[$key]['votes'] = $value['votes'];
                       $data[$key]['category_id'] = $value['category_id'];
                       $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['message']))))), 0, 100, 'UTF-8', '...');

                       $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                       $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);
                           
                       $data[$key]['type'] = 1;

                       $user_infos = $this->model('account')->get_user_info_by_uid($value['uid']);

                       $data[$key]['user_name'] = $user_infos['user_name'];

                       $data[$key]['avatar_file'] = get_avatar_url($value['uid'],'max');
                      
                       preg_match_all($pattern,htmlspecialchars_decode($value['question_detail']),$match);

                       $data[$key]['img'] = $match[src][0];
                      
                       if($data[$key]['img'])
                       {
                            $data[$key]['img'] = base_url().$data[$key]['img'];
                       }

                       $data[$key]['article_img'] = $value['article_img'];

                       $data[$key]['message'] = '发表了文章';
                       $data[$key]['fid'] = $value['column_id'];
                       // $data[$key]['comment'] = $this->model('article')->get_comments($value['id'], 0, 3);
                  }
            }



            return $data;
        }


        public function get_answer_list_by_question_id($question_id, $limit = 20, $where = null, $order = 'answer_id DESC',$page=0)
        {
            if ($where)
            {
                $_where = ' AND (' . $where . ')';
            }

            if ($answer_list = $this->fetch_all('answer', 'is_del = 0 and question_id = ' . intval($question_id) . $_where, $order, $limit,$page*$limit))
            {
                foreach($answer_list as $key => $val)
                {
                    $uids[] = $val['uid'];

                    $answer_list[$key]['add_time']=date_friendly($val['add_time']);

                    $answer_list[$key]['comments']['comments_info']=$this->get_answer_comments($val['answer_id'],3);
                    $answer_list[$key]['comments']['count']=$this->count('answer_comments','is_del=0 and answer_id='.$val['answer_id']);
                }
            }

            if ($uids)
            {
                if ($users_info = $this->model('account')->get_user_info_by_uids($uids, true))
                {
                    foreach($answer_list as $key => $val)
                    {
                        $answer_list[$key]['user_info'] = $users_info[$val['uid']];
                        $answer_list[$key]['user_info']['avatar'] = get_avatar_url($val['uid'],'max');
                        unset($answer_list[$key]['ip']);
                        unset($answer_list[$key]['user_info']['reg_ip']);
                        unset($answer_list[$key]['user_info']['last_ip']);
                    }
                }
            }

            return $answer_list;
        }



        public function fetch_column_list($uid , $page = 0, $limit = 10, $sort_type = 'sum' ,$type = false)
        {   

            $focus_column = $this->fetch_all('column_focus', 'uid = ' . intval($uid));

            if($focus_column)
            {
                foreach ($focus_column as $key => $value) {
                   $column_ids[] = $value['column_id'];
                }
            }

            $order = 'focus_count DESC,sort ASC';

            if($column_ids)
            {
                $where = 'is_verify = 1 and column_id not in ('.implode(',', $column_ids).')';
                
            }else
            {
                $where = 'is_verify = 1';
            }

            $list = $this->fetch_page('column', $where , $order , $page, $limit);

            if(!empty($list)){

                foreach ($list as $key => $val) {

                    $list[$key] = $val;

                    $list[$key]['has_focus_column'] = $this->model('column')->has_focus_column($uid, $val['column_id']);

                    $list[$key]['views_count'] = $this->model('column')->get_column_views_num($val['column_id']);

                    $list[$key]['article_count'] = $this->model('column')->get_column_article_num($val['column_id']);

                    $list[$key]['votes_count'] = $this->model('column')->get_column_votes_num($val['column_id']);

                    $views[$key] = $list[$key]['views_count'];

                    $articles[$key] = $list[$key]['article_count'];

                    $addtime[$key] = $list[$key]['add_time'];

                    $sort[$key] = $list[$key]['sort'];
                }

                
                switch ($sort_type) {
                    case 'new':
                        array_multisort($addtime, SORT_DESC, SORT_NUMERIC ,$list);
                        break;
                    case 'hot':
                        array_multisort($articles, SORT_DESC,$views, SORT_DESC,$list);
                        break;
                    case 'sum':
                        array_multisort($articles, SORT_DESC, $sort , SORT_ASC,$list);
                        break;
                }
            }

            return $list;
        }


        public function parse_at_user($content, $popup = false, $with_user = false, $to_uid = false)
        {
            preg_match_all('/@([^@,:\s,]+)/i', strip_tags($content), $matchs);

            if (is_array($matchs[1]))
            {
                $match_name = array();

                foreach ($matchs[1] as $key => $user_name)
                {
                    if (in_array($user_name, $match_name))
                    {
                        continue;
                    }

                    $match_name[] = $user_name;
                }

                $match_name = array_unique($match_name);

                arsort($match_name);

                $all_users = array();

                $content_uid = $content;

                foreach ($match_name as $key => $user_name)
                {
                    if (preg_match('/^[0-9]+$/', $user_name))
                    {
                        $user_info = $this->model('account')->get_user_info_by_uid($user_name);
                    }
                    else
                    {
                        $user_info = $this->model('account')->get_user_info_by_username($user_name);
                    }

                    if ($user_info)
                    {
                        $content = str_replace('@' . $user_name, '<a href="people/' . $user_info['uid'] . '"' . (($popup) ? ' target="_blank"' : '') . ' class="aw-user-name" data-id="' . $user_info['uid'] . '">@' . $user_info['user_name'] . '</a>', $content);

                        if ($to_uid)
                        {
                            // $content_uid = str_replace('@' . $user_name, '@' . $user_info['uid'], $content_uid);

                            $content_uid = str_replace('@' . $user_name,'', $content_uid);
                        }

                        if ($with_user)
                        {
                            $all_users[] = $user_info['uid'];
                        }
                    }
                }
            }

            if ($with_user)
            {
                return $all_users;
            }

            if ($to_uid)
            {   
                $info['message'] = FORMAT::parse_links($content_uid);
                
                if($user_info)
                {
                    $info['atuser'] = array('user_name' => $user_info['user_name'],'uid' => $user_info['uid']);
                }else
                {
                    $info['atuser'] = null;
                }

                return $info;
            }

            return $content;
        }


        public function get_answer_comments($answer_id,$limit=5,$page=0,$order=null)
        {   
            return $this->fetch_all('answer_comments', "is_del = 0 and answer_id = " . intval($answer_id), $order,$limit,$page*$limit);
        }

        public function get_article_comments($article_id,$limit=5,$page=0,$order=null)
        {    
            return $this->fetch_all('article_comments', "is_del = 0 and article_id = " . intval($article_id), $order,$limit,$page*$limit);
        }

        public function get_question_comments($question_id,$limit=5,$page=0,$order=null)
        {
            return $this->fetch_all('question_comments', "is_del = 0 and question_id = " . intval($question_id), $order,$limit,$page*$limit);
        }
        
        public function get_users($uid,$topics,$page){
                
                if($topics)
                {
                     $sql = "select distinct item_id from ".get_table('topic_relation')." where type = 'question' and is_del = 0 and topic_id in (".$topics.")";

                     $question_info_ids = $this->query_all($sql);
                     

                     foreach ($question_info_ids as $key => $value) {
                         $question_ids[] = $value['item_id'];
                     }
                     
                     //联表查询 先查问题并且一对多关联回复 得到所有有效回复的uid
                     $sql = "select a.uid,u.user_name,count(a.answer_id) as number from ".get_table('answer')." a left join ".get_table('users')." u on u.uid = a.uid where a.uid != $uid and a.question_id in (".implode(',', $question_ids).") group by a.uid order by number DESC limit ".calc_page_limit($page,10);

                     $ret = $this->query_all($sql);
                     
                     return $ret;
                }

                return false;
        }


        public function get_user_answer_number($uid,$topics,$user_name)
        {   
            if($user_name)
            {
                $res = $this->model('account')->fetch_all('users',"user_name like '%".$user_name."%' and uid != ".$uid,'reputation desc',10);

            }else
            {
                $res = $this->model('account')->fetch_all('users',"uid != ".$uid,'reputation desc',10);
            }
            
            if($topics)
            {
                $sql = "select distinct item_id from ".get_table('topic_relation')." where type = 'question' and is_del = 0 and topic_id in (".$topics.")";

                $question_info_ids = $this->query_all($sql);

                foreach ($question_info_ids as $key => $value) {
                     $question_ids[] = $value['item_id'];
                }  
            }

            foreach ($res as $key => $value) {
                 $ret[$key]['uid'] = $value['uid'];
                 $ret[$key]['user_name'] = $value['user_name'];
                 $ret[$key]['number'] = $this->m_get_user_answer_number($value['uid'],$question_ids? :null);
            }

            return $ret;
            
        }

        public function m_get_user_answer_number($uid,$question_ids)
        {    
             //联表查询 先查问题并且一对多关联回复 得到所有有效回复的uid
             if($question_ids)
             {
                $sql = "select count(a.answer_id) as number from ".get_table('answer')." a left join ".get_table('users')." u on u.uid = a.uid where a.uid = ".$uid." and a.question_id in (".implode(',', $question_ids).") group by a.uid";
 
                return $this->query_all($sql)[0]['number']? :0;

             }else
             {
                return 0;
             }
        }


        public function search($q, $search_type, $page = 1, $limit = 20)
        {
            if (!$q)
            {
                return false;
            }

            $q = (array)explode(' ', str_replace('  ', ' ', trim($q)));
 
            foreach ($q AS $key => $val)
            {
                if (strlen($val) == 1)
                {
                    unset($q[$key]);
                }
            }

            if (!$q)
            {
                return false;
            }

            if (!$search_type)
            {
                $search_type = 'users,topics,questions,articles';
            }

            $result_list = $this->model('search')->get_mixed_result($search_type, $q, $topic_ids, $page, $limit);
           
            if ($result_list)
            {
                foreach ($result_list as $result_info)
                {
                    $result = $this->prase_result_info_api($result_info,$q);
                    

                    if (is_array($result))
                    {
                        $data[] = $result;
                    }
                }
            }

            return $data;
        }


        public function prase_result_info_api($result_info,$q)
        {   
   
            $type = null;$users  = null;$articles = null;$questions = null;$topics = null;
            
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';
            
            if (isset($result_info['last_login']))
            {
                if(!$user_info = $this->model('account')->get_user_info_by_uid($result_info['uid'], true))
                {
                     return array(
                        'users' => $users,
                        'articles' => $articles,
                        'questions' => $questions,
                        'topics' => $topics,
                        'type' => $type
                     );
                }

                $users['type'] = $type = 'users';

                $users['search_id'] = $user_info['uid'];

                $users['name'] = $user_info['user_name'];

                $users['detail'] = array(
                    'uid' => $user_info['uid'],
                    'user_name' => $user_info['user_name'],
                    'avatar_file' => get_avatar_url($user_info['uid'], 'mid'),  // 头像
                    'signature' => $user_info['signature'], // 签名
                    'reputation' => $user_info['reputation'],   // 签名
                    'agree_count' =>  $user_info['agree_count'],    // 赞同
                    'fans_count' =>  $user_info['fans_count'],  // 关注数
                );
            }
            else if ($result_info['topic_id'])
            {
                if(!$topic = $this->fetch_row('topic','topic_id ='.$result_info['topic_id']))
                {
                    return array(
                        'users' => $users,
                        'articles' => $articles,
                        'questions' => $questions,
                        'topics' => $topics,
                        'type' => $type
                     );
                }

                $topics['type'] = $type = 'topics';

                $topics['search_id'] = $topic['topic_id'];

                $topics['name'] = $topic['topic_title'];

                $topics['detail'] = array(
                    'topic_pic'=> get_topic_pic_url('mid', $topic['topic_pic']),
                    'topic_id' => $topic['topic_id'],   // 话题 ID
                    'focus_count' => $topic['focus_count'],
                    'discuss_count' => $topic['discuss_count'], // 讨论数量
                    'discuss_count_last_week' => $topic['discuss_count_last_week'], // 7天讨论数量
                    'discuss_count_last_month' => $topic['discuss_count_last_month'],   // 30天讨论数量
                    'topic_description' => $topic['topic_description'],
                    'topic_title' => $topics['name']
                );
            }
            else if ($result_info['question_id'])
            {
                $is_question_detail = false;

                if(!$question = $this->fetch_row('question','question_id='.$result_info['question_id']))
                {
                    return array(
                        'users' => $users,
                        'articles' => $articles,
                        'questions' => $questions,
                        'topics' => $topics,
                        'type' => $type
                     );
                }

                $questions['type'] = $type = 'questions';

                $user_info = $this->model('account')->get_user_info_by_uid($question['published_uid'], true);

                $questions['search_id'] = $question['question_id'];

                $questions['name'] = $question['question_content'];
      
                $q1 = preg_replace('/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/', '', $this->quote(implode(' ', $q)))? :$this->quote(implode(' ', $q));
                
                $answer = $this->query_all("select * from ".$this->get_table('answer')." where is_del = 0 and question_id = ".$question['question_id']." and answer_content like '%".$q1."%' order by agree_count DESC,add_time DESC limit 1");
                
                $sum_len = 40;//总截取长度
     
                if(!empty($answer[0]))//如果回复中存在关键字
                {   

                    $answer = $answer[0];

                    preg_match_all($pattern,htmlspecialchars_decode($answer['answer_content']),$out);

                    $question['img'] = $out[src];
                    
                    if($question['img'])
                    {
                        foreach ($question['img'] as $k => $v) {
                            $question['img'][$k] = base_url().$v;
                        }
                    }
                    
                    $question['answer_name'] = $this->fetch_one('users','user_name','uid='.$answer['uid']);
                    
                    $answer['answer_content'] = preg_replace('/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i','<a href="question/'.$questions['search_id'].'__answer_id-'.$answer['answer_id'].'">[图片]</a>',htmlspecialchars_decode($answer['answer_content']));

                    $answer['answer_content'] = preg_replace('/<video([^>]+)>(.)*<\/video>/i','<a href="question/'.$questions['search_id'].'">[视频]</a>',htmlspecialchars_decode($answer['answer_content']));

                    $answer['answer_content'] = strip_tags($answer['answer_content']);
                    
                    $w = mb_strpos($answer['answer_content'],$q1);

                    if($w || $w === 0)
                    {   
                        if(mb_strlen($answer['answer_content']) > $sum_len)
                        {    
                            $start = $w - $sum_len > 0 ? $w - $sum_len : 0;
                            
                            $end = mb_strlen($answer['answer_content']) - $w  > $sum_len ? $w + $sum_len : mb_strlen($answer['answer_content']);
                             
                            $question['question_detail'] = mb_substr($answer['answer_content'],$start,$end,'UTF-8');
         
                        }else
                        {
                            $question['question_detail'] = mb_substr($answer['answer_content'],0, $sum_len ,'UTF-8');
                        }

                    }else
                    {
                         $is_question_detail = true;   
                    }

                }else
                {   
                    $is_question_detail = true;   
                }

                if($is_question_detail)
                {   
                    
                    preg_match_all($pattern,htmlspecialchars_decode($question['question_detail']),$out);

                    $question['img'] = $out[src];
                    
                    if($question['img'])
                    {
                        foreach ($question['img'] as $k => $v) {
                            $question['img'][$k] = base_url().$v;
                        }
                    }

                    $question['question_detail'] = preg_replace('/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i','<a href="question/'.$questions['search_id'].'">[图片]</a>',htmlspecialchars_decode($question['question_detail']));
                    
                    $question['question_detail'] = preg_replace('/<video([^>]+)>(.)*<\/video>/i','<a href="question/'.$questions['search_id'].'">[视频]</a>',htmlspecialchars_decode($question['question_detail']));
                    
                    
                    $question['question_detail'] = strip_tags($question['question_detail']);
      
                    $question['answer_name'] = null;

                    $question['question_detail'] = $question['question_detail'];
                    
                    $w = mb_strpos($question['question_detail'],$q1);
                    
                    if($w || $w === 0)
                    {   
                        if(mb_strlen($question['question_detail']) > $sum_len)
                        {    
                            $start = $w - $sum_len > 0 ? $w - $sum_len : 0;
                            
                            $end = mb_strlen($question['question_detail']) - $w  > $sum_len ? $w + $sum_len : mb_strlen($question['question_detail']);
                             
                            $question['question_detail'] = mb_substr($question['question_detail'],$start,$end,'UTF-8');
         
                        }else
                        {
                            $question['question_detail'] = mb_substr($question['question_detail'],0, $sum_len ,'UTF-8');
                        }

                    }else
                    {   

                        $question['question_detail'] = $question['question_detail'];
                    }

                }

                 $question['question_detail'] = str_replace('&nbsp;','',$question['question_detail']);

                 $question['question_detail'] = str_replace('&nbsp','',$question['question_detail']);


                 $search_result[$key][$val['type']]['name'] = preg_replace("/$q/i", "<span class='aw-text-color-red'>$q</span>", $val[$val['type']]['name']);


                 $questions['detail'] = array(
                    // 'best_answer' => $result_info['best_answer'],    // 最佳回复 ID
                    'uid' => $user_info['uid'], //作者
                    'user_name' => $user_info['user_name'], //作者名
                    'avatar_file' => get_avatar_url($user_info['uid'], 'mid'),  // 头像
                    'answer_count' => $question['answer_count'],    // 回复数
                    'focus_count' => $question['focus_count'],
                    'agree_count' => $question['agree_count'],
                    'add_time' => date_friendly($question['add_time']),
                    'answer_name' => $question['answer_name'],
                    'question_detail' => $question['question_detail'],
                    'question_content' => $questions['name'],
                    'answer_id' => $is_question_detail?0:$answer['answer_id'],
                    'update_time' => date_friendly($question['update_time']),
                    'img' => $question['img']? :[]
                );
            }
            else if ($result_info['id'])
            {
                if(!$article = $this->fetch_row('article','id='.$result_info['id']))
                {
                     return array(
                        'users' => $users,
                        'articles' => $articles,
                        'questions' => $questions,
                        'topics' => $topics,
                        'type' => $type
                     );
                }

                $articles['type'] = $type = 'articles';

                $articles['search_id'] = $article['id'];

                $user_info = $this->model('account')->get_user_info_by_uid($article['uid'], true);

                $articles['name'] = $article['title'];
                
                preg_match_all($pattern,htmlspecialchars_decode($article['message']),$out);

                $article['img'] = $out[src];
                
                if($article['img'])
                {
                    foreach ($article['img'] as $k => $v) {
                        $article['img'][$k] = base_url().$v;
                    }
                }

                $article['message'] = preg_replace('/<\s*img\s+[^>]*?src\s*=\s*(\'|\")(.*?)\\1[^>]*?\/?\s*>/i','<a href="article/'.$articles['search_id'].'">[图片]</a>',htmlspecialchars_decode($article['message']));

                $sum_len = 40;//总截取长度
                
                $q1 = preg_replace('/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/', '', $this->quote(implode(' ', $q)))? :$this->quote(implode(' ', $q));

                $article['message'] = strip_tags($article['message']);
                
                $w = mb_strpos($article['message'],$q1);
                    
                if($w || $w === 0)
                {   
                    
                    if(mb_strlen($article['message']) > $sum_len)
                    {   
                        $start = $w - $sum_len > 0 ? $w - $sum_len : 0;
                        
                        $end = mb_strlen($article['message']) - $w  > $sum_len ? $w + $sum_len : mb_strlen($article['message']);
                        
                        $article['message'] = mb_substr($article['message'],$start,$end,'UTF-8');

                    }else
                    {
                        $article['message'] = mb_substr($article['message'],0, $sum_len ,'UTF-8');
                    }

                }else
                {
                    $article['message'] = mb_substr($article['message'],0, $sum_len ,'UTF-8');
                }

                $article['message'] = str_replace('&nbsp;','',$article['message']);

                $article['message'] = str_replace('&nbsp','',$article['message']);

                $articles['detail'] = array(
                    'uid' => $user_info['uid'], //作者
                    'user_name' => $user_info['user_name'], //作者名
                    'avatar_file' => get_avatar_url($user_info['uid'], 'mid'),  // 头像
                    'focus_count' => $article['focus_count'],
                    'comments' => $article['comments'],
                    'content' =>$info,
                    'votes' => $article['votes'],
                    'add_time' => date_friendly($article['add_time']),
                    'answer_name' => $article['answer_name'],
                    'message' => $article['message'],
                    'title' => $articles['name'],
                    'update_time' => date_friendly($article['update_time']),
                    'img' => $article['img']? :[]
                );
            }

            return array(
                'users' => $users,
                'articles' => $articles,
                'questions' => $questions,
                'topics' => $topics,
                'type' => $type
            );
        

        }


        public function search_topics_title($q, $page = null, $limit = 10,$column=array())
        {
            if (!$q){return false;}

            $q = (array)explode(' ', str_replace('  ', ' ', trim($q)));

            foreach ($q AS $key => $val)
            {
                if (strlen($val) == 1)
                {
                    unset($q[$key]);
                }
            }

            if (!$q){return false;}
            
            if (is_array($q))
            {
                $q = implode('', $q);
            }

            $limits = $page && $limit ? calc_page_limit($page, $limit) : null;

            if ($result = $this->fetch_all('topic', "topic_title LIKE '%" . $this->quote($q) . "%'", null, $limits ,0,$column))
            {
                foreach ($result AS $key => $val)
                {
                    if (!$val['url_token'])
                    {
                        $result[$key]['url_token'] = urlencode($val['topic_title']);
                    }
                }
            }

            return $result;
        }


        //根据uid查找用户回答列表
        public function get_answers_by_uid($uid)
        {
            if (!$uid)
            {
                return false;
            }

            if ($answers = $this->fetch_all('answer', "uid = " . intval($uid)))
            {
                foreach ($answers AS $key => $val)
                {
                    $result[$val['answer_id']] = $val;
                }
            }

            return $result;
        }


        public function question_data($where,$page,$order='update_time desc',$limit = 10){
           
            $ret=$this->fetch_page('question',$where,$order,$page,$limit,true,['update_time','question_content','answer_count','votes as agree_count','view_count','question_detail','question_id','anonymous','published_uid']);
            
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';

            foreach ($ret as $key => $value) {
                 
                 $data[$key]['post_type'] = 'question';
                 $data[$key]['title'] = cjk_substr($value['question_content'], 0, 30, 'UTF-8', '...');
                 
                 $data[$key]['add_time'] = date_friendly($value['add_time']);
                 $data[$key]['update_time'] = date_friendly($value['update_time']);
                 
                 $data[$key]['comments'] = $value['answer_count'];
                 $data[$key]['views'] = $value['view_count'];
                 $data[$key]['votes'] = $value['agree_count'];
                 $data[$key]['category_id'] = $value['category_id'];

                 $answer=$this->fetch_row('answer','is_del=0 and question_id='.$value['question_id'].' and uid = '.$value['published_uid'],'answer_id desc');
                
                 $data[$key]['comments'] = $value['answer_count'];
                 $data[$key]['views'] = $value['view_count'];
                 $data[$key]['votes'] = $value['agree_count'];
                 $data[$key]['category_id'] = $value['category_id'];

                 if($answer)
                 {   

                     $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($answer['answer_content']))))), 0, 100, 'UTF-8', '...');

                     $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                     $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);


                     preg_match_all($pattern,htmlspecialchars_decode($answer['answer_content']),$match);

                     $data[$key]['img'] = $match[src][0];
                    
                     if($data[$key]['img'])
                     {
                          $data[$key]['img'] = base_url().$data[$key]['img'];
                     }

                     $user_info = $this->model('account')->get_user_info_by_uid($answer['uid']);
                     $data[$key]['uid'] = $answer['uid'];
                    
                     $user_infos = $this->model('account')->get_user_info_by_uid($value['answer_info']['uid']);

                     $data[$key]['user_name'] = $user_info['user_name'];
                     $data[$key]['avatar_file'] = get_avatar_url($answer['uid'],'max');
                     $data[$key]['itemId'] = $answer['answer_id'];
                     $data[$key]['message'] = '回复了问题';
                     $data[$key]['anonymous'] = $answer['anonymous'];
                     $data[$key]['fid'] = $value['question_id'];
                     $data[$key]['type'] = 3;

                 }else
                 {   

                     $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['question_detail']))))), 0, 100, 'UTF-8', '...');

                     $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                     $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);

                     preg_match_all($pattern,htmlspecialchars_decode($value['question_detail']),$match);

                     $data[$key]['img'] = $match[src][0];
                    
                     if($data[$key]['img'])
                     {
                          $data[$key]['img'] = base_url().$data[$key]['img'];
                     }

                     $user_info = $this->model('account')->get_user_info_by_uid($value['published_uid']);
                     $data[$key]['uid'] = $value['published_uid'];
                     $data[$key]['user_name'] = $user_info['user_name'];
                     $data[$key]['avatar_file'] = get_avatar_url($value['published_uid'],'max');
                     $data[$key]['itemId'] = $value['question_id'];
                     $data[$key]['message'] = '发起了问题';
                     $data[$key]['anonymous'] = $value['anonymous'];
                     $data[$key]['fid'] = 0;
                     $data[$key]['type'] = 2;
                 }

                 $data[$key]['article_img'] = '';
            }

            return $data;
        }


        public function article_data($where,$page,$order='add_time desc',$limit = 10){
        //$ret=$this->fetch_all('question',$where,'add_time desc',10,$page*10,['add_time','question_content','answer_count','agree_count','question_detail','question_id','is_reward','reward_money']);
            $ret=$this->fetch_page('article',$where,$order,$page,$limit);
            
            $user_infos = $this->model('account')->get_user_info_by_uids(fetch_array_value($ret, 'uid'));

            foreach ($ret as $key => $value) {

                $value['message']=str_replace('&nbsp;', '', $value['message']);
               
                $message=replacePicUrl(htmlspecialchars_decode(html_entity_decode($value['message'])),base_url());
                preg_match_all('/<img[^>]*src=[\'"]?([^>\'"\s]*)[\'"]?[^>]*>/i',$question_detail,$match);
               
                $ret[$key]['img']=[];
                $ret[$key]['message']=strip_tags($message);
                if($match[1] and !preg_replace("/[\s\v".chr(194).chr(160)."]+$/","",strip_tags($message))){
                    $ret[$key]['message']='[图片]';
                }

                $ret[$key]['user_info'] = $user_infos[$value['uid']];

            }

            return $ret;
        }


        public function favitor_data($uid,$page,$order='add_time desc'){
            $sql="select a.answer_id,b.item_id,a.anonymous,a.answer_content,a.uid,c.question_id,a.add_time,c.update_time,c.question_content,c.category_id,c.answer_count,c.agree_count,c.view_count,c.question_detail,b.time from ".$this->get_table('answer')." a left join ".$this->get_table('favorite')." b on a.answer_id=b.item_id left join ".$this->get_table('question')." c on a.question_id=c.question_id where b.type='answer' and a.is_del = 0 and c.is_del = 0 and b.uid= $uid";
            $ret=$this->query_all($sql);
            
            $pattern='/<img((?!src).)*src[\s]*=[\s]*[\'"](?<src>[^\'"]*)[\'"]/i';

            foreach ($ret as $key => $value) {
                
                 if($value['item_id'])
                  $answer=$this->fetch_row('answer','answer_id='.$value['item_id']);
                 
                 $data[$key]['post_type'] = 'question';
                 $data[$key]['title'] = cjk_substr($value['question_content'], 0, 30, 'UTF-8', '...');
                 
                 $data[$key]['add_time'] = date_friendly($value['add_time']);
                 $data[$key]['update_time'] = date_friendly($value['update_time']);
                 $data[$key]['comments'] = $value['answer_count'];
                 $data[$key]['views'] = $value['view_count'];
                 $data[$key]['votes'] = $value['agree_count'];
                 $data[$key]['category_id'] = $value['category_id'];

                 if($answer)
                 {   
                     $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($answer['answer_content']))))), 0, 100, 'UTF-8', '...');

                     $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                     $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);


                     preg_match_all($pattern,htmlspecialchars_decode($answer['answer_content']),$match);

                     $data[$key]['img'] = $match[src][0];
                    
                     if($data[$key]['img'])
                     {
                          $data[$key]['img'] = base_url().$data[$key]['img'];
                     }

                     $user_info = $this->model('account')->get_user_info_by_uid($answer['uid']);
                     $data[$key]['uid'] = $answer['uid'];
                     $data[$key]['user_name'] = $user_info['user_name'];
                     $data[$key]['avatar_file'] = get_avatar_url($answer['uid'],'max');
                     $data[$key]['itemId'] = $answer['answer_id'];
                     $data[$key]['message'] = '回复了问题';
                     $data[$key]['anonymous'] = $answer['anonymous'];
                     $data[$key]['fid'] = $value['question_id'];
                     $data[$key]['type'] = 3;

                 }else
                 {   

                     $data[$key]['content'] = cjk_substr(preg_replace('/\[attach\]([0-9]+)\[\/attach]/', '', strip_tags(html_entity_decode(FORMAT::parse_attachs(FORMAT::parse_bbcode($value['question_detail']))))), 0, 100, 'UTF-8', '...');

                     $data[$key]['content'] = str_replace('&nbsp;','',$data[$key]['content']);

                     $data[$key]['content'] = str_replace('&nbsp','',$data[$key]['content']);

                     preg_match_all($pattern,htmlspecialchars_decode($value['question_detail']),$match);

                     $data[$key]['img'] = $match[src][0];
                    
                     if($data[$key]['img'])
                     {
                          $data[$key]['img'] = base_url().$data[$key]['img'];
                     }

                     $user_info = $this->model('account')->get_user_info_by_uid($value['published_uid']);
                     $data[$key]['uid'] = $value['published_uid'];
                     $data[$key]['user_name'] = $user_info['user_name'];
                     $data[$key]['avatar_file'] = get_avatar_url($value['published_uid'],'max');
                     $data[$key]['itemId'] = $value['question_id'];
                     $data[$key]['message'] = '发起了问题';
                     $data[$key]['anonymous'] = $value['anonymous'];
                     $data[$key]['fid'] = 0;
                     $data[$key]['type'] = 2;
                 }

                 $data[$key]['article_img'] = '';
                
        }
        return $data;
    }
    
    //根据uid 获取收藏列表
    public function get_favorite_by_uid($where = null, $order = null, $page = null, $limit = 10, $rows_cache = true,$column=array()){
        
        $list = $this->fetch_page('favorite', $where, $order, $page, $limit,  true,$column);
        return $list;
    }


    public function get_articles_list($column_id, $page, $per_page, $order_by, $day = null ,$uid = 0 , $filter = '')
    {
        $where = array();

        if ($column_id)
        {
            $where[] = 'column_id = ' . intval($column_id);
        }

        if ($day)
        {
            $where[] = 'add_time > ' . (time() - $day * 24 * 60 * 60);
        }

        if ($uid)
        {
            $where[] = 'uid = ' . $uid;
        }
              $where[] = 'is_del = 0';
        
        $category_ids = $this->model('column')->check_suggest();
              
        if(!empty($category_ids)){

              $where[] = 'category_id not in ('.implode(',',$category_ids) .')';
              
        }

        return $this->fetch_page('article', implode(' AND ', $where), $order_by, $page, $per_page);
    }


    //获取用户草稿
    public function get_draft_page($where, $order = 'id DESC', $limit = 10, $page = null)
    {
        if (!$where)
        {
            return false;
        }
        
        $drafts = $this->fetch_page('draft', $where, $order, $page, $limit);
      
        $re = array();
        foreach ($drafts as $key => $value) {
            $value['data'] = unserialize($value['data'])['message'];
            $re[] = $value;
        }
  
        return $re;
    }


    public function get_user_fans_by_uid($uid,$page,$per_page)
    {
        if (!$user_fans = $this->fetch_page('user_follow', 'is_del = 0 and  friend_uid = ' . intval($uid), 'add_time DESC',$page,$per_page))
        {
            return false;
        }

        foreach ($user_fans AS $key => $val)
        {
            $fans_uids[$val['fans_uid']] = $val['fans_uid'];
        }
        return $this->model('account')->get_user_info_by_uids($fans_uids, true);
    }


    //获取用户关注的问答 分页
    public function get_focus_question_page_by_uid($uid, $order = 'focus_id DESC', $limit = 10, $page = null,$column=array())
    {
        if (!$uid)
        {
            return false;
        }
        
        $question_focus = $this->fetch_page('question_focus', "uid = " . intval($uid), $order, $page, $limit,true,$column);

        foreach ($question_focus as $key => $val)
        {
            $list[$val['question_id']] = $val;
        }

        return $list;
    }


    //获取用户关注的专栏 分页
    public function get_focus_column_page_by_uid($uid, $order = 'focus_id DESC', $limit = 10, $page = null)
    {
        if (!$uid)
        {
            return false;
        }
        
        $column_focus = $this->fetch_page('column_focus', "uid = " . intval($uid), $order, $page, $limit);
        
        foreach ($column_focus as $key => $val)
        {
            $list[$val['column_id']] = $val;
        }

        return $list;
    }


    //获取用户关注的用户 分页
    public function get_focus_users_page_by_uid($uid, $order = 'follow_id DESC', $limit = 10, $page = null,$column=array())
    {
        if (!$uid)
        {
            return false;
        }
        $focus = $this->fetch_page('user_follow', "is_del=0 and fans_uid = " . intval($uid), $order, $page, $limit,true,$column);

        foreach ($focus as $key => $val)
        {
            $list[$val['friend_uid']] = $val;
        }

        return $list;
    }


    public function bind_app($data,$uid)
    {   
        if(!$uid)
        {
            return false;
        }

        if ($data['weiboUser'] != null) {
            
            if (get_setting('sina_weibo_enabled') != 'Y' OR !get_setting('sina_akey') OR !get_setting('sina_skey'))
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站未开通微博登录')));
            }

            $loginWeibo = $data['weiboUser'];
            //传入的微博信息
            if($loginWeibo['access_token']){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微博accessToken不能为空')));

            } 

            if($loginWeibo['id']){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('新浪用户 ID不能为空')));
                
            }

            $weiboUser = $this->model('openid_weibo_oauth')->get_weibo_user_by_id($loginWeibo['id']);

            if($weiboUser){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微博账号已被绑定')));
                    
            }else if ($weibo_user = $this->model('openid_weibo_oauth')->get_weibo_user_by_uid($uid)){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('该账号已经绑定过微博')));

            }else{
                
                $loginWeibo['uid'] = $uid;

                $loginWeibo['add_time'] = time();


                if(!$loginWeibo['expiresTime'])
                {
                     $loginWeibo['expiresTime'] = time();
                }else
                {
                     $loginWeibo['expiresTime'] = date('yyyy-MM-dd HH:mm:ss',strtotime($loginWeibo['expiresTime']));
                }
                
                //微博绑定 插入数据
                $this->model('openid_weibo_oauth')->bind_account($loginWeibo, $uid);

                if (!$this->model('integral')->fetch_log($uid, 'BIND_OPENID'))
                {
                    $this->model('integral')->process($uid, 'BIND_OPENID', round((get_setting('integral_system_config_profile') * 0.2)), '绑定 OPEN ID');
                }

                return true;
            }

        }if ($data['weixinUser'] != null) {

            $loginWeixin = $data['weixinUser'];
            //传入的微信信息
            if(!$loginWeixin['openid']){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微信openid不能为空')));

            }

            $weixinUser = $this->model('openid_weixin_weixin')->get_user_info_by_openid($loginWeixin['openid']);

            if($weixinUser){

                    H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微信账号已被绑定')));
                    
            }else if ($weixin_user = $this->model('openid_weixin_weixin')->get_user_info_by_uid($uid)){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('该账号已经绑定过微信')));

            }else{

                if(!$loginWeixin['nickname'])
                {
                    H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('参数错误')));
                }

                $loginWeixin['uid'] = $uid;

                $loginWeixin['add_time'] = time();
                
                if(!$loginWeixin['expires_in'])
                {
                     $loginWeixin['expires_in'] = date('yyyy-MM-dd HH:mm:ss',time());

                }else
                {
                     $loginWeixin['expires_in'] = date('yyyy-MM-dd HH:mm:ss',strtotime($loginWeixin['expires_in']));
                }
                
                //微信绑定 插入数据
                $this->model('openid_weixin_weixin')->bind_account($loginWeixin,$loginWeixin,$uid,true);

                if (!$this->model('integral')->fetch_log($uid, 'BIND_OPENID'))
                {
                    $this->model('integral')->process($uid, 'BIND_OPENID', round((get_setting('integral_system_config_profile') * 0.2)), '绑定 OPEN ID');
                }

                return true;

                
             }

        }if ($data['qqUser'] != null) {
            

            if (get_setting('qq_login_enabled') != 'Y' OR !get_setting('qq_login_app_id') OR !get_setting('qq_login_app_key'))
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站未开通 QQ 登录')));
            }

            $loginQQ = $data['qqUser'];
            //传入的QQ信息
            if($loginQQ['openid']){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('QQopenid不能为空')));

            }

            $qqUser = $this->model('openid_qq')->get_qq_user_by_openid($loginQQ['openid']);

            if($qqUser){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('QQ账号已被绑定')));
                    
            }else if ($qq_user = $this->model('openid_qq')->get_qq_user_by_uid($uid)){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('该账号已经绑定过QQ')));

            }else{

                if(!$loginQQ['nickname'])
                {
                    H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('参数错误')));
                }

                $loginQQ['uid'] = $uid;

                $loginQQ['add_time'] = time();

                if(!$loginQQ['expires_time'])
                {
                     $loginQQ['expires_time'] = time();
                }else
                {
                     $loginQQ['expires_time'] = date('yyyy-MM-dd HH:mm:ss',strtotime($loginQQ['expires_time']));
                }
                
                //QQ绑定 插入数据
                $this->model('openid_qq')->bind_account($loginQQ,$uid);

                if (!$this->model('integral')->fetch_log($uid, 'BIND_OPENID'))
                {
                    $this->model('integral')->process($uid, 'BIND_OPENID', round((get_setting('integral_system_config_profile') * 0.2)), '绑定 OPEN ID');
                }

                return true;

                
            }

        }

        return false;
    }


    public function get_user_info_by_min_openid($open_id)
    {
        return $this->fetch_row('users_minweixin', "openid = '" . $this->quote($open_id) . "'");
    }


    public function get_user_info_by_uid($uid)
    {
        return $this->fetch_row('users_minweixin', 'uid = ' . intval($uid));
    }


    public function bind_account($access_user, $access_token, $uid, $is_ajax = false)
    {
        if (! $access_user['nickName'])
        {
            if ($is_ajax)
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('与微信通信出错, 请重新登录')));
            }
            else
            {
                H::redirect_msg(AWS_APP::lang()->_t('与微信通信出错, 请重新登录'));
            }
        }

        if ($openid_info = $this->get_user_info_by_uid($uid))
        {
            if ($openid_info['openid'] != $access_user['openId'])
            {
                if ($is_ajax)
                {
                    H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微信账号已经被其他账号绑定')));
                }
                else
                {
                    H::redirect_msg(AWS_APP::lang()->_t('微信账号已经被其他账号绑定'));
                }
            }

            return true;
        }

        $this->insert('users_minweixin', array(
            'uid' => intval($uid),
            'openid' => $access_token['openId'],
            'expires_in' => (time() + $access_token['expires_in']),
            'access_token' => $access_token['access_token'],
            'refresh_token' => $access_token['refresh_token'],
            'scope' => $access_token['scope'],
            'headimgurl' => $access_user['avatarUrl'],
            'nickname' => $access_user['nickName'],
            'sex' => $access_user['gender'],
            'province' => $access_user['province'],
            'city' => $access_user['city'],
            'country' => $access_user['country'],
            'add_time' => time(),
            'unionid' => $access_user['unionId'],
        ));
        
        // $this->associate_avatar($uid, $access_user['headimgurl']);

        // $this->model('account')->associate_remote_avatar($uid, $access_user['headimgurl']);

        return true;
    }

    //小程序微信绑定
    public function bind_min_weixin_app($data,$uid)
    {   
        if(!$uid)
        {
            return false;
        }

        if ($data != null) {

            $loginWeixin = $data;
            //传入的微信信息
            if(!$loginWeixin['openId']){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微信openid不能为空')));

            }

            $weixinUser = $this->get_user_info_by_min_openid($loginWeixin['openId']);

            if ($weixin_user = $this->get_user_info_by_uid($uid)){

                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('该账号已经绑定过微信')));

            }else{

                if(!$loginWeixin['nickName'])
                {
                    H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('参数错误')));
                }

                $loginWeixin['uid'] = $uid;

                $loginWeixin['add_time'] = time();
                
                if(!$loginWeixin['expires_in'])
                {
                     $loginWeixin['expires_in'] = date('yyyy-MM-dd HH:mm:ss',time());

                }else
                {
                     $loginWeixin['expires_in'] = date('yyyy-MM-dd HH:mm:ss',strtotime($loginWeixin['expires_in']));
                }
                
                //微信绑定 插入数据
                $this->bind_account($loginWeixin,$loginWeixin,$uid,true);

                if (!$this->model('integral')->fetch_log($uid, 'BIND_OPENID'))
                {
                    $this->model('integral')->process($uid, 'BIND_OPENID', round((get_setting('integral_system_config_profile') * 0.2)), '绑定 OPEN ID');
                }

                return true;
                
             }

        }

        return false;
    }
    

    public function update_user_info($id, $weixin_user)
    {
        if (!is_digits($id))
        {
            return false;
        }

        return $this->update('users_weixin', array(
            'openid' => $weixin_user['openid'],
            'expires_in' => (time() + $weixin_user['expires_in']),
            'access_token' => $weixin_user['access_token'],
            'refresh_token' => $weixin_user['refresh_token'],
            'scope' => $weixin_user['scope'],
            'headimgurl' => $weixin_user['headimgurl'],
            'nickname' => $weixin_user['nickname'],
            'sex' => $weixin_user['sex'],
            'province' => $weixin_user['province'],
            'city' => $weixin_user['city'],
            'country' => $weixin_user['country'],
        ), 'id = ' . $id);
    }


    public function update_min_user_info($id, $weixin_user)
    {
        if (!is_digits($id))
        {
            return false;
        }

        return $this->update('users_minweixin', array(
            'openid' => $weixin_user['openid'],
            'expires_in' => (time() + $weixin_user['expires_in']),
            'access_token' => $weixin_user['access_token'],
            'refresh_token' => $weixin_user['refresh_token'],
            'scope' => $weixin_user['scope'],
            'headimgurl' => $weixin_user['headimgurl'],
            'nickname' => $weixin_user['nickname'],
            'sex' => $weixin_user['sex'],
            'province' => $weixin_user['province'],
            'city' => $weixin_user['city'],
            'country' => $weixin_user['country'],
        ), 'id = ' . $id);
    }

    public function removeLinks($str){
         if(empty($str))return    '';
         $str    =preg_replace('/(http)(.)*([a-z0-9\-\.\_])+/i','',$str);
         $str    =preg_replace('/(www)(.)*([a-z0-9\-\.\_])+/i','',$str);
         return    $str;
    }

    public function get_message_by_dialog_id($dialog_id,$order = 'id ASC')
    {
      if ($inbox = $this->fetch_all('inbox', 'dialog_id = ' . intval($dialog_id), $order))
      {
        foreach ($inbox AS $key => $val)
        {
          $message[$val['id']] = $val;
        }
      }

      return $message;
    }

    public function get_answers_by_ids($answer_ids)
    {
      if (!is_array($answer_ids))
      {
        return false;
      }

      if ($answers = $this->fetch_all('answer', "answer_id IN (" . implode(', ', $answer_ids) . ") and is_del = 0"))
      {
        foreach ($answers AS $key => $val)
        {
          $result[$val['answer_id']] = $val;
        }
      }

      return $result;
    }


    //获取session_key
    public function get_sessionKey($appid, $secret,$js_code)
    {
        if (!$appid OR !$secret OR !$js_code)
        {
            return false;
        }
        $result = curl_get_contents('https://api.weixin.qq.com/sns/jscode2session?appid='.$appid.'&secret='.$secret.'&js_code='.$js_code.'&grant_type=authorization_code');
        return json_decode($result,true);
    }
    
} 
