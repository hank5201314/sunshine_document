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

define('IN_AJAX', TRUE);


if (!defined('IN_ANWSION'))
{
    die;
}

include_once "xcx/wxBizDataCrypt.php";

define('IN_MOBILE', true);

class weixin_ajax extends AWS_CONTROLLER
{
    public function get_access_rule()
    {
        $rule_action['rule_type'] = 'white';
        $rule_action['actions'] = array(
            'login_process',//登录
            'register_process',//注册并绑定小程序账号
            'search_result',//搜索
            'logout',//退出
            'app_bind',//APP绑定与登录
            'weixin_login_process',//小程序登录
            'register_weixin_process',//小程序注册
            // 'app_register',
            // 'app_login',
            
            'find_password_modify',//手机号找回密码第二步
            'first_find_password',//手机号找回密码
            'find_password_email',//邮箱找回密码
            'user_actions',
            'favorite_list',
            'search_focus_topics',
            'list',

            'save_invite',//邀请
            'cancel_question_invite',//取消邀请
            'focus_question',//关注问题
            'follow_people',//关注用户
            'lock_question',//锁定问题
            'remove_question_comment',//删除问题评论
            'set_question_recommend',//设置问题推荐
            'remove_question',//删除问题
            'answer_vote',//回复赞踩
            'article_vote',//文章赞踩
            'update_favorite_tag',//收藏
            'remove_favorite_item',//删除收藏
            'focus_all',//首页关注全部
            'publish_question',//发布问题
            'modify_question',//修改问题
            'save_answer',//发布回复
            'update_answer',//修改回复
            'save_question_comment',//评论问题
            'save_answer_comment',//评论回复
            'save_article_comment',//评论文章
            'publish_article',//发布文章
            'modify_article',//修改文章
            'question_thanks',//问题感谢
            'question_answer_rate',//回复感谢
            'save_report',//举报
            'one_best_answer',//采纳
            'read_notification',//更新通知状态
            'send',//发送私信
            'del_inbox',//删除私信
            'save_privacy',//设置用户信息
            'article_logo_upload',//文章封面
            'column_logo_upload',//专栏
            'avatar_upload',//头像
            'img_upload',//问题文章回复图片
            'focus_column',//关注专栏
            'apply_column',//创建专栏
            'edit_column',//编辑专栏
            'remove_article_comment',//删除文章评论
            'set_article_recommend',//设置文章推荐
            'remove_article',//删除文章
            'search_tag',//搜索话题
            'search_question',//搜索问题
            'save_draft',//保存草稿
            'profile_setting',//保存资料
            'privacy_setting',
            'bind_mobile',//绑定手机号
            'bind_mobile_one',//旧手机解绑确认
            'save_password',//修改密码
            'unbind_app',//APP解除绑定
            'focus_topic',
            'check_user_mobile',

        );

        return $rule_action;
    }

    public function setup()
    {   
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
            
        $this->per_page = get_setting('contents_per_page');//每页总数

        HTTP::no_cache_header();
       
    } 

    //修改密码
    public function save_password_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$_POST['old_password'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请输入当前密码')));
        }

        if ($_POST['password'] != $_POST['re_password'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('两次输入的密码不一致')));
        }

        if (strlen($_POST['password']) < 6 OR strlen($_POST['password']) > 16)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入6-16位的新密码')));
        }

        if (get_setting('ucenter_enabled') == 'Y')
        {
            if ($this->model('ucenter')->is_uc_user($this->user_info['email']))
            {
                $result = $this->model('ucenter')->user_edit($this->user_id, $this->user_info['user_name'], $_POST['old_password'], $_POST['password']);

                if ($result !== 1)
                {
                    H::ajax_json_output(AWS_APP::RSM(null, -1, $result));
                }
            }
        }

        if ($this->model('account')->update_user_password($_POST['old_password'], $_POST['password'], $this->user_id, $this->user_info['salt']))
        {   
            // $this->model('account')->logout();

            H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('密码修改成功, 请牢记新密码')));
        }
        else
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请输入正确的当前密码')));
        }
        
    }

    /*
      获取标签(搜索标签)
    */
    public function search_tag_action()
    {
        $_POST['q']=preg_replace('/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/', '', trim($_POST['q']));

        if(!trim($_POST['q'])){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请输入关键词")));
        }
        
        $result = $this->model('api')->search_topics_title(cjk_substr($_POST['q'], 0, 64),null,null,['topic_id','topic_title','url_token']);
        
        H::ajax_json_output(AWS_APP::RSM($result, 1, null));
    }

    /*
      搜索问题
    */
    public function search_question_action()
    {
        $_POST['q']=preg_replace('/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/', '', trim($_POST['q']));

        if(!trim($_POST['q'])){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请输入关键词")));
        }
        
        $result = $this->model('question')->get_by_like($_POST['q'], 1, 10);

        foreach ($result as $key => $value) {
            $data[$key]['question_content'] = $value['question_content'];
            $data[$key]['question_id'] = $value['question_id'];
            $data[$key]['answer_count'] = $value['answer_count'];
            $data[$key]['focus_count'] = $value['focus_count'];
        }
        
        H::ajax_json_output(AWS_APP::RSM($data? :null, 1,null));
    }

    //搜索
    public function search_result_action()
    {   
        if (!in_array($_POST['search_type'], array('questions', 'topics', 'users', 'articles')))
        {
            $_POST['search_type'] = null;
        }

        $_POST['q']=preg_replace('/[\'.,:;*?~`!@#$%^&+=)(<>{}]|\]|\[|\/|\\\|\"|\|/', '', trim($_POST['q']));

        $search_result = $this->model('api')->search(cjk_substr($_POST['q'], 0, 64), $_POST['search_type'], $_POST['page'], get_setting('contents_per_page'));
        
        $q = $_POST['q'];
        
        if ($this->user_id AND $search_result)
        {
            foreach ($search_result AS $key => $val)
            {   
                switch ($val['type'])
                {
                    case 'questions':
                        $search_result[$key]['questions']['detail']['focus'] = $this->model('question')->has_focus_question($val['search_id'], $this->user_id);

                        // $search_result[$key]['questions']['detail']['question_detail'] = preg_replace("/$q/i", "<view class='blues'>$q</view>", $val[$val['type']]['detail']['question_detail'])? :$val['questions']['detail']['question_detail'];
                        

                        break;

                    case 'articles':
                        // $search_result[$key]['articles']['detail']['message'] = preg_replace("/$q/i", "<view class='blues'>$q</view>", $val[$val['type']]['detail']['message'])? :$val['articles']['detail']['message'];

                        break;

                    case 'topics':
                        $search_result[$key]['topics']['detail']['focus'] = $this->model('topic')->has_focus_topic($this->user_id, $val['search_id']);

                        break;

                    case 'users':
                        $search_result[$key]['users']['detail']['focus'] = $this->model('follow')->user_follow_check($this->user_id, $val['search_id']);

                        break;
                }
            }
        }
        
        if(!$search_result)
        {  

           H::ajax_json_output(AWS_APP::RSM(null, 1, null));
        }else
        {
            H::ajax_json_output(AWS_APP::RSM($search_result, 1, null));
        }
        
    }


    //注册
    public function register_process_action()
    {
        if (get_setting('register_type') == 'close')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站目前关闭注册')));
        }
        else if (get_setting('register_type') == 'invite' AND !$_POST['icode'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站只能通过邀请注册')));
        }
        else if (get_setting('register_type') == 'weixin')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站只能通过微信注册')));
        }

        if (trim($_POST['user_name']) == '')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入用户名')));
        }
        else if ($this->model('account')->check_username($_POST['user_name']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户名已经存在')));
        }
        else if ($check_rs = $this->model('account')->check_username_char($_POST['user_name']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户名包含无效字符')));
        }
        else if ($this->model('account')->check_username_sensitive_words($_POST['user_name']) OR trim($_POST['user_name']) != $_POST['user_name'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户名中包含敏感词或系统保留字')));
        }

        $regist_type = $_POST['type'] ? $_POST['type'] : 'email';
        if($regist_type == 'email'){
            if ($this->model('account')->check_email($_POST['email']))
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('E-Mail 已经被使用, 或格式不正确')));
            }
        } elseif ($regist_type == 'mobile'){

            hook('mobile_regist','register',array('mobile'=>$_POST['mobile'],'smscode'=>$_POST['smscode']));
        }

        if (strlen($_POST['password']) < 6 OR strlen($_POST['password']) > 16)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('密码不能为空，请输入6-16位的密码')));
        }

        // if (! $_POST['agreement_chk'])
        // {
        //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('你必需同意用户协议才能继续')));
        // }

        // 检查验证码
        /*if (!AWS_APP::captcha()->is_validate($_POST['seccode_verify']) AND get_setting('register_seccode') == 'Y' and $regist_type=='email')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请填写正确的验证码')));
        }*/

        // if(get_setting('register_seccode') == 'Y' and $regist_type=='email' and !$this->model('tools')->geetest($_POST)){
        //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('验证码错误')));
        // }

        if (get_setting('ucenter_enabled') == 'Y')
        {
            $result = $this->model('ucenter')->register($_POST['user_name'], $_POST['password'], $_POST['email']);

            if (is_array($result))
            {
                $uid = $result['user_info']['uid'];
            }
            else
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, $result));
            }
        }
        else
        {
            $uid = $this->model('account')->user_register($_POST['user_name'], $_POST['password'], $_POST['email'],$_POST['mobile'],$regist_type);
        }


        if ($_POST['email'] == $invitation['invitation_email'])
        {
            $this->model('active')->set_user_email_valid_by_uid($uid);

            $this->model('active')->active_user_by_uid($uid);

            $this->model('active')->new_valid_email($uid);
        }

        if (isset($_POST['sex']))
        {
            $update_data['sex'] = intval($_POST['sex']);

            if ($_POST['province'])
            {
                $update_data['province'] = htmlspecialchars($_POST['province']);
                $update_data['city'] = htmlspecialchars($_POST['city']);
            }

            if ($_POST['job_id'])
            {
                $update_data['job_id'] = intval($_POST['job_id']);
            }

            $update_attrib_data['signature'] = htmlspecialchars($_POST['signature']);

            // 更新主表
            $this->model('account')->update_users_fields($update_data, $uid);

            // 更新从表
            $this->model('account')->update_users_attrib_fields($update_attrib_data, $uid);
        }

        $this->model('account')->logout();

        if (get_setting('register_valid_type') == 'N' OR (get_setting('register_valid_type') == 'email' AND get_setting('register_type') == 'invite'))
        {
            $this->model('active')->active_user_by_uid($uid);
        }

        $user_info = $this->model('account')->get_user_info_by_uid($uid);

        $user_info['avatar_file'] = get_avatar_url($user_info['uid'],'max');

        $key = $this->model('api')->setcookie_login($user_info['uid'], $user_info['user_name'], $user_info['password'], $user_info['salt'], null, false);

        AWS_APP::cache()->set(base64_encode($user_info['user_name']),$user_info['uid'],60 * 60 * 24);

        if(empty($this->user_info['email_settings']))
        {
             unset($this->user_info['email_settings']);
        }

        if(empty($this->user_info['weixin_settings']))
        {
             unset($this->user_info['weixin_settings']);
        }

        if (get_setting('register_valid_type') == 'N' OR $user_info['group_id'] != 3 OR $_POST['email'] == $invitation['invitation_email'])
        {   
            H::ajax_json_output(AWS_APP::RSM(array(
                    'key' => $key,
                    'user'=> $user_info,
                    'wechat' => base64_encode($user_info['user_name'])
                ), 1, AWS_APP::lang()->_t('注册成功')));
        }
        else
        {
            AWS_APP::session()->valid_email = $user_info['email'];

            $this->model('active')->new_valid_email($uid);

            H::ajax_json_output(AWS_APP::RSM(array(
                    'key' => $key,
                    'user'=> $user_info,
                    'wechat' => base64_encode($user_info['user_name'])
                ), 1, AWS_APP::lang()->_t('请进行邮箱验证')));
           
        }

    }

    //注册
    public function register_weixin_process_action()
    {   
        $data = json_decode($_POST['data'],true);

        if(!$data)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('小程序用户数据异常')));  
        }

        $weixinUser = $this->model('api')->get_user_info_by_min_openid($data['openId']);

        if($weixinUser){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微信账号已被绑定')));
        }

        if (get_setting('register_type') == 'close')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站目前关闭注册')));
        }
        else if (get_setting('register_type') == 'invite' AND !$_POST['icode'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站只能通过邀请注册')));
        }
        else if (get_setting('register_type') == 'weixin')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('本站只能通过微信注册')));
        }

        if (trim($_POST['user_name']) == '')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入用户名')));
        }
        else if ($this->model('account')->check_username($_POST['user_name']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户名已经存在')));
        }
        else if ($check_rs = $this->model('account')->check_username_char($_POST['user_name']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户名包含无效字符')));
        }
        else if ($this->model('account')->check_username_sensitive_words($_POST['user_name']) OR trim($_POST['user_name']) != $_POST['user_name'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户名中包含敏感词或系统保留字')));
        }

        $regist_type = $_POST['type'] ? $_POST['type'] : 'email';

        if($regist_type == 'email'){
            if ($this->model('account')->check_email($_POST['email']))
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('E-Mail 已经被使用, 或格式不正确')));
            }
        } elseif ($regist_type == 'mobile'){

            hook('mobile_regist','register',array('mobile'=>$_POST['mobile'],'smscode'=>$_POST['smscode']));
        }

        if (strlen($_POST['password']) < 6 OR strlen($_POST['password']) > 16)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('密码不能为空，请输入6-16位的密码')));
        }

        if (get_setting('ucenter_enabled') == 'Y')
        {
            $result = $this->model('ucenter')->register($_POST['user_name'], $_POST['password'], $_POST['email']);

            if (is_array($result))
            {
                $uid = $result['user_info']['uid'];
            }
            else
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, $result));
            }
        }
        else
        {
            $uid = $this->model('account')->user_register($_POST['user_name'], $_POST['password'], $_POST['email'],$_POST['mobile'],$regist_type);
        }

        if ($_POST['email'] == $invitation['invitation_email'])
        {
            $this->model('active')->set_user_email_valid_by_uid($uid);

            $this->model('active')->active_user_by_uid($uid);

            $this->model('active')->new_valid_email($uid);
        }

        $this->model('account')->logout();

        if (get_setting('register_valid_type') == 'N' OR (get_setting('register_valid_type') == 'email' AND get_setting('register_type') == 'invite'))
        {
            $this->model('active')->active_user_by_uid($uid);
        }

        $user_info = $this->model('account')->get_user_info_by_uid($uid);

        $user_info['avatar_file'] = get_avatar_url($user_info['uid'],'max');
        
        //小程序绑定相关数据

        if($data)
        {   
            // file_put_contents('./log3.txt',var_export($data,true)."\n",FILE_APPEND);

            $this->model('api')->bind_min_weixin_app($data,$user_info['uid']);
        }


        $key = $this->model('api')->setcookie_login($user_info['uid'], $user_info['user_name'], $user_info['password'], $user_info['salt'], null, false);
        
        AWS_APP::cache()->set(base64_encode($user_info['user_name']),$user_info['uid'],60 * 60 * 24);

        if(empty($this->user_info['email_settings']))
        {
             unset($this->user_info['email_settings']);
        }

        if(empty($this->user_info['weixin_settings']))
        {
             unset($this->user_info['weixin_settings']);
        }

        if (get_setting('register_valid_type') == 'N' OR $user_info['group_id'] != 3 OR $_POST['email'] == $invitation['invitation_email'])
        {   
            H::ajax_json_output(AWS_APP::RSM(array(
                    'key' => $key,
                    'user'=> $user_info,
                    'wechat' => base64_encode($user_info['user_name'])
                ), 1, AWS_APP::lang()->_t('注册成功')));
        }
        else
        {
            AWS_APP::session()->valid_email = $user_info['email'];

            $this->model('active')->new_valid_email($uid);

            H::ajax_json_output(AWS_APP::RSM(array(
                    'key' => $key,
                    'user'=> $user_info,
                    'wechat' => base64_encode($user_info['user_name'])
                ), 1, AWS_APP::lang()->_t('请进行邮箱验证')));
           
        }

    }

    //再次发送邮件
    public function send_valid_mail_action()
    {
        if (!$this->user_id)
        {
            if ( H::valid_email(AWS_APP::session()->valid_email))
            {
                $this->user_info = $this->model('account')->get_user_info_by_email(AWS_APP::session()->valid_email);
                $this->user_id = $this->user_info['uid'];
            }
        }

        if (! H::valid_email($this->user_info['email']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('错误, 用户没有提供 E-mail')));
        }

        if ($this->user_info['valid_email'] == 1)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('用户邮箱已经认证')));
        }

        $this->model('active')->new_valid_email($this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('邮件发送成功')));
    }

    //登陆
    public function login_process_action()
    {   
        $data = json_decode($_POST['data'],true);
       
        if(!trim($_POST['user_name'])){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请填写账号')));
        }
                        
        if(!trim($_POST['password'])){
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请填写密码')));
        }

        if (get_setting('ucenter_enabled') == 'Y')
        {
            if (!$user_info = $this->model('ucenter')->login($_POST['user_name'], $_POST['password']))
            {
                $user_info = $this->model('account')->check_login($_POST['user_name'], $_POST['password']);
            }
        }
        else
        {
            $user_name=trim($_POST['user_name']);
            if(get_hook_info('mobile_regist')['state']==1){
                $login_type=get_hook_config('mobile_regist')['login_type']['value'];
                if(preg_match("/^1[345789]\d{9}$/", $user_name)){
                    if($login_type=='email')
                        H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('当前只能用邮箱或者用户名登录')));
                    else
                        $user_info = $this->get_user_info_action($_POST['user_name'], $_POST['password']);
                }else if(H::valid_email($user_name)){
                    if($login_type=='mobile')
                        H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('当前只能用手机号或者用户名登录')));
                    else
                        $user_info = $this->get_user_info_action($_POST['user_name'], $_POST['password']);
                        
                }else{
                    $user_info = $this->get_user_info_action($_POST['user_name'], $_POST['password']);
                }
            }else{
                $user_info = $this->get_user_info_action($_POST['user_name'], $_POST['password']);

            }
        }

        if (!$user_info)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入正确的帐号或密码')));
        }
        else if ($user_info == 'no_user_name')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入正确的帐号')));
        }
        else if ($user_info == 'no_password')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入正确的密码')));
        }
        else
        {
            if ($user_info['forbidden'] == 1)
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('抱歉, 你的账号已经被禁止登录')));
            }

            if (get_setting('site_close') == 'Y' AND $user_info['group_id'] != 1)
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, get_setting('close_notice')));
            }
            
            if ($_POST['net_auto_login'])
            {
                $expire = 60 * 60 * 24 * 360;
            }
            
            $this->model('account')->update_user_last_login($user_info['uid']);

            $this->model('account')->logout();

//            $key = $this->model('api')->setcookie_login($user_info['uid'], $user_info['user_name'], $_POST['password'], $user_info['salt'], $expire);
            $key = md5($user_info['uid'].time());
            AWS_APP::cache()->set($key ,$user_info['uid'],60 * 60 * 24);

            if($data)
            {
                $this->model('api')->bind_min_weixin_app($data,$user_info['uid']);
            }

            if(empty($this->user_info['email_settings']))
            {
                 unset($this->user_info['email_settings']);
            }

            if(empty($this->user_info['weixin_settings']))
            {
                 unset($this->user_info['weixin_settings']);
            }

            $user_info['avatar_file'] = get_avatar_url($user_info['uid'],'max');
            
            H::ajax_json_output(AWS_APP::RSM(array(
                'key' => $key,
                'user'=> $user_info,
                'wechat' => $key
            ), 1, null));
            
        }
    }

    //邀请用户回答
    public function save_invite_action()
    {   

        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在或已被删除')));
        }

        if (!$invite_user_info = $this->model('account')->get_user_info_by_uid($_POST['uid']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('用户不存在')));
        }

        if ($invite_user_info['uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('不能邀请自己回复问题')));
        }

        if ($this->user_info['integral'] < 0 and get_setting('integral_system_enabled') == 'Y')
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余积分已经不足以进行此操作')));
        }

        if ($this->model('answer')->has_answer_by_uid($_POST['question_id'], $invite_user_info['uid']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('该用户已经回答过该问题')));
        }

        if ($question_info['published_uid'] == $invite_user_info['uid'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('不能邀请问题的发起者回答问题')));
        }

        if ($this->model('question')->has_question_invite($_POST['question_id'], $invite_user_info['uid']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('该用户已接受过邀请')));
        }

        if ($this->model('question')->has_question_invite($_POST['question_id'], $invite_user_info['uid'], $this->user_id))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('已邀请过该用户')));
        }

        $this->model('question')->add_invite($_POST['question_id'], $this->user_id, $invite_user_info['uid']);

        $this->model('account')->update_question_invite_count($invite_user_info['uid']);

        if ($weixin_user = $this->model('openid_weixin_weixin')->get_user_info_by_uid($invite_user_info['uid']) AND $invite_user_info['weixin_settings']['QUESTION_INVITE'] != 'N')
        {
            $this->model('weixin')->send_text_message($weixin_user['openid'], "有会员在问题 [" . $question_info['question_content'] . "] 邀请了你进行回答", $this->model('openid_weixin_weixin')->redirect_url('/m/question/' . $question_info['question_id']));
        }

        $notification_id = $this->model('notify')->send($this->user_id, $invite_user_info['uid'], notify_class::TYPE_INVITE_QUESTION, notify_class::CATEGORY_QUESTION, intval($_POST['question_id']), array(
            'from_uid' => $this->user_id,
            'question_id' => intval($_POST['question_id'])
        ));

        $this->model('email')->action_email('QUESTION_INVITE', $_POST['uid'], get_js_url('/question/' . $question_info['question_id'] . '?notification_id-' . $notification_id), array(
            'user_name' => $this->user_info['user_name'],
            'question_title' => $question_info['question_content'],
        ));

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    //取消邀请用户回答
    public function cancel_question_invite_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $this->model('question')->cancel_question_invite($_POST['question_id'], $this->user_id, $_POST['uid']);

        $this->model('account')->update_question_invite_count($_POST['uid']);
        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    //关注问题
    public function focus_question_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$_POST['question_id'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
        }

        if (! $this->model('question')->get_question_info_by_id($_POST['question_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'type' => $this->model('question')->add_focus_question($_POST['question_id'], $this->user_id)
        ), 1, null));
    }

    //关注专栏
    public function focus_column_action()
    {
        if (!$_POST['column_id'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('专栏不存在')));
        }

        if (!$this->model('column')->get_column_by_id($_POST['column_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('专栏不存在')));
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'type' => $this->model('column')->add_focus_column($this->user_id, intval($_POST['column_id']))
        ), '1', null));
    }


    public function follow_people_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (! $_POST['uid'] OR $_POST['uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('无法关注自己')));
        }

        // 首先判断是否存在关注
        if ($this->model('follow')->user_follow_check($this->user_id, $_POST['uid']))
        {
            $action = 'remove';

            $this->model('follow')->user_follow_del($this->user_id, $_POST['uid']);
        }
        else
        {
            $action = 'add';

            $this->model('follow')->user_follow_add($this->user_id, $_POST['uid']);

            $this->model('notify')->send($this->user_id, $_POST['uid'], notify_class::TYPE_PEOPLE_FOCUS, notify_class::CATEGORY_PEOPLE, $this->user_id, array(
                'from_uid' => $this->user_id
            ));

            $this->model('email')->action_email('FOLLOW_ME', $_POST['uid'], get_js_url('/people/' . $this->user_info['uid']), array(
                'user_name' => $this->user_info['user_name'],
            ));
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'type' => $action
        ), 1, null));
    }


    public function remove_question_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有删除问题的权限')));
        }

        if ($question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']))
        {
            if ($this->user_id != $question_info['published_uid'])
            {
                $this->model('account')->send_delete_message($question_info['published_uid'], $question_info['question_content'], $question_info['question_detail']);
            }

            $this->model('question')->remove_question($question_info['question_id']);

        }else
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    public function set_question_recommend_action()
    {   

        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有设置推荐的权限')));
        }

        switch ($_POST['action'])
        {
            case 'set':
                $this->model('question')->set_recommend($_POST['question_id']);
            break;

            case 'unset':
                $this->model('question')->unset_recommend($_POST['question_id']);
            break;
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }


    public function remove_article_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有删除文章的权限')));
        }

        if ($article_info = $this->model('article')->get_article_info_by_id($_POST['article_id']))
        {
            if ($this->user_id != $article_info['uid'])
            {
                $this->model('account')->send_delete_message($article_info['uid'], $article_info['title'], $article_info['message']);
            }

            $this->model('article')->remove_article($article_info['id']);
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    public function set_article_recommend_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有设置推荐的权限')));
        }

        switch ($_POST['action'])
        {
            case 'set':
                $this->model('article')->set_recommend($_POST['article_id']);
            break;

            case 'unset':
                $this->model('article')->unset_recommend($_POST['article_id']);
            break;
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    public function remove_question_comment_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (! in_array($_POST['type'], array(
            'answer',
            'question'
        )))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('错误的请求')));
        }

        if (!$_POST['comment_id'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('评论不存在')));
        }

        $comment = $this->model($_POST['type'])->get_comment_by_id($_POST['comment_id']);

        if (! $this->user_info['permission']['is_moderator'] AND ! $this->user_info['permission']['is_administortar'] AND $this->user_id != $comment['uid'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('你没有权限删除该评论')));
        }

        $this->model($_POST['type'])->remove_comment($_POST['comment_id']);

        if ($_POST['type'] == 'question')
        {
            $this->model('question')->update_question_comments_count($comment['question_id']);
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    public function remove_article_comment_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有删除评论的权限')));
        }

        if ($comment_info = $this->model('article')->get_comment_by_id($_POST['comment_id']))
        {
            $this->model('article')->remove_comment($comment_info['id']);
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    public function lock_question_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (! $this->user_info['permission']['is_moderator'] AND ! $this->user_info['permission']['is_administortar'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('你没有权限进行此操作')));
        }

        if (! $question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('问题不存在')));
        }

        $this->model('question')->lock_question($_POST['question_id'], !$question_info['lock']);

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }


    public function answer_vote_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $answer_info = $this->model('answer')->get_answer_by_id($_POST['answer_id']);

        if ($answer_info['uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('不能对自己发表的回复进行点赞')));
        }

        if (! in_array($_POST['value'], array(
            - 1,
            1
        )))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('点赞数据错误, 无法进行点赞')));
        }

        $reputation_factor = $this->model('account')->get_user_group_by_id($this->user_info['reputation_group'], 'reputation_factor');

        $this->model('answer')->change_answer_vote($_POST['answer_id'], $_POST['value'], $this->user_id, $reputation_factor);

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }


    public function article_vote_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        switch ($_POST['type'])
        {
            case 'article':
                $item_info = $this->model('article')->get_article_info_by_id($_POST['item_id']);
            break;

            case 'comment':
                $item_info = $this->model('article')->get_comment_by_id($_POST['item_id']);
            break;
        }

        if (!$item_info)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('内容不存在')));
        }

        if ($item_info['uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('不能对自己发表的内容进行点赞')));
        }

        $reputation_factor = $this->model('account')->get_user_group_by_id($this->user_info['reputation_group'], 'reputation_factor');

        $this->model('article')->article_vote($_POST['type'], $_POST['item_id'], $_POST['rating'], $this->user_id, $reputation_factor, $item_info['uid']);

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }


    public function update_favorite_tag_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $tags = array(
          0 => '默认标签',
        );

        $this->model('favorite')->add_favorite($_POST['item_id'], $_POST['item_type'], $this->user_id);
        
        foreach ($tags as $va) {
            if (rtrim($va, ',') != '')
            {
                $this->model('favorite')->update_favorite_tag($_POST['item_id'], $_POST['item_type'], $va, $this->user_id);
            }
        }
        H::ajax_json_output(AWS_APP::RSM(null, 1, '收藏成功'));
    }


    public function remove_favorite_item_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $this->model('favorite')->remove_favorite_item($_POST['item_id'], $_POST['item_type'], $this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }


    /*关注所有*/
    public function focus_all_action(){

        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $type=trim($_POST['type']);
        $ids=trim($_POST['item_ids']);
        if(!$type)
            H::ajax_json_output(AWS_APP::RSM(null, -1, '类型不能为空'));
        if(empty($ids))
            H::ajax_json_output(AWS_APP::RSM(null, -1, 'item_ids不能为空'));
        $ids=explode(',',$ids);
        if($type=='users'){
             foreach ($ids as $key => $value) {
                if($this->user_id!=$value){
                    
                    if ($this->model('follow')->user_follow_check($this->user_id, $value))
                    {
                       continue;
                    }
                    $this->model('follow')->user_follow_add($this->user_id,$value);
                }
             }
        }
        if($type=='column') {
            foreach ($ids as $key => $value) {
                if ($this->model('column')->has_focus_column($this->user_id, $value)) {
                    continue;
                    H::ajax_json_output(AWS_APP::RSM(null, 1, '您已关注'));
                }
                $this->model('column')->add_focus_column($this->user_id, $value);
            }
        }
        H::ajax_json_output(AWS_APP::RSM(null, 1, '关注成功'));

    }

    public function save_report_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (trim($_POST['reason']) == '')
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请填写举报理由')));
        }

        $has = 0;

        if($_POST['type'] == 'question')
        {   
            if (! $question_info = $this->model('question')->fetch_row('question','question_id='.intval($_POST['target_id'])))
            {   
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('问题不存在')));
            }

            if($question_info['published_uid'] == $this->user_id)
            {
                $has = 1;
            }

            $url = base_url().'/question/'.intval($_POST['target_id']);
        }
        else if($_POST['type'] == 'article')
        {   
   
            if (!$article_info = $this->model('article')->get_article_info_by_id(intval($_POST['target_id'])))
            {
               H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('文章不存在')));
            }

            if($article_info['uid'] == $this->user_id)
            {
                $has = 1;
            }


            $url = base_url().'/article/'.$_POST['target_id'];
        }
        else if($_POST['type'] == 'question_answer')
        {   
            if(!$_POST['fid'])
            {
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('问题不存在')));
            }

            if(! $answer_info=$this->model('answer')->get_answer_by_id(intval($_POST['target_id'])))
            {
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('回复不存在')));
            }

            if($answer_info['uid'] == $this->user_id)
            {
                $has = 1;
            }

            $url = base_url().'/question/'.intval($_POST['fid']).'#!answer_'.intval($_POST['target_id']);

        }else if($_POST['type'] == 'article_answer')
        {   
            if(!$_POST['fid'])
            {
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('文章不存在')));
            }

            if(!$article_answer_info=$this->model('api')->fetch_row('article_comments','id = '.intval($_POST['target_id'])))
            {
                H::ajax_json_output(AWS_APP::RSM(null, -2, AWS_APP::lang()->_t('评论不存在')));
            }

            if($article_answer_info['uid'] == $this->user_id)
            {
                $has = 1;
            }

            $url = base_url().'/article/'.intval($_POST['fid']).'#!answer_'.intval($_POST['target_id']);

        }

        if($has == 1)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('无法对自己发表的内容进行举报')));
        }
        
        $this->model('question')->save_report($this->user_id, $_POST['type'], $_POST['target_id'], htmlspecialchars($_POST['reason']), $url);

        $recipient_uid = get_setting('report_message_uid') ? get_setting('report_message_uid') : 1;

        //$this->model('message')->send_message($this->user_id, $recipient_uid, AWS_APP::lang()->_t('有新的举报, 请登录后台查看处理: %s', get_js_url('/admin/question/report_list/')));
        $message = AWS_APP::lang()->_t('有新的举报, 请登录后台查看处理: %s', get_js_url('/admin/question/report_list/'));
        $this->model('notify')->send(0, $recipient_uid, notify_class::TYPE_REPORT, notify_class::CATEGORY_QUESTION,$_POST['target_id'],array(
            'title'=>$message,'from_uid'=>$recipient_uid
        ));

        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('举报成功')));
    }


    public function question_thanks_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if ($this->user_info['integral'] < 0 AND get_setting('integral_system_enabled') == 'Y')
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余积分已经不足以进行此操作')));
        }

        if (!$question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
        }

        if ($question_info['published_uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('不能感谢自己的问题')));
        }

        if ($this->model('question')->question_thanks($_POST['question_id'], $this->user_id, $this->user_info['user_name']))
        {
            $this->model('notify')->send($this->user_id, $question_info['published_uid'], notify_class::TYPE_QUESTION_THANK, notify_class::CATEGORY_QUESTION, $_POST['question_id'], array(
                'question_id' => intval($_POST['question_id']),
                'from_uid' => $this->user_id
            ));

            H::ajax_json_output(AWS_APP::RSM(array(
                'action' => 'add'
            ), 1, null));
        }
        else
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题已感谢')));
        }
    }

    public function question_answer_rate_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(!$answer_info = $this->model('answer')->get_answer_by_id($_POST['answer_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('回复不存在')));
        }

        if ($this->user_id == $answer_info['uid'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('不能感谢自己发表的回复')));
        }

        if(trim($_POST['type']) != 'thanks')
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('类型异常')));
        }

        if (trim($_POST['type']) == 'thanks' AND $this->model('answer')->user_rated('thanks', $_POST['answer_id'], $this->user_id))
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('已感谢过该回复, 请不要重复感谢')));
        }

        if ($this->user_info['integral'] < 0 and get_setting('integral_system_enabled') == 'Y' and $_POST['type'] == 'thanks')
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余积分已经不足以进行此操作')));
        }

        if ($this->model('answer')->user_rate(trim($_POST['type']), $_POST['answer_id'], $this->user_id, $this->user_info['user_name']))
        {
            if ($answer_info['uid'] != $this->user_id)
            {
                $this->model('notify')->send($this->user_id, $answer_info['uid'], notify_class::TYPE_ANSWER_THANK, notify_class::CATEGORY_QUESTION, $answer_info['question_id'], array(
                    'question_id' => $answer_info['question_id'],
                    'from_uid' => $this->user_id,
                    'item_id' => $answer_info['answer_id']
                ));
            }

            H::ajax_json_output(AWS_APP::RSM(array(
                'action' => 'add'
            ), 1, null));
        }
        else
        {
            H::ajax_json_output(AWS_APP::RSM(array(
                'action' => 'remove'
            ), 1, null));
        }
    }

    public function one_best_answer_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$answer_info = $this->model('answer')->get_answer_by_id($_POST['answer_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('回答不存在')));
        }
        if ($answer_info['uid']==$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('不能将自己的回答设为最佳回复')));
        }
        if (! $question_info = $this->model('question')->get_question_info_by_id($answer_info['question_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('问题不存在')));
        }
        if ($answer_info['uid']==$question_info['published_uid'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('不能将发帖者的回答设为最佳回复')));
        }
        if ($question_info['published_uid'] != $this->user_id AND ! $this->user_info['permission']['is_moderator'] AND ! $this->user_info['permission']['is_administortar'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('你没有权限进行此操作')));
        }
        $ubest_count=$this->model('answer')->count('answer','is_best=1 and question_id='.$question_info['question_id'].' and uid='.$answer_info['uid']);
        if($ubest_count>0){
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('同一个回复者的多个回答只能设置一个最佳回复')));
        }
        $best_count=$this->model('answer')->count('answer','is_best=1 and question_id='.$question_info['question_id']);
        if ($best_count>=1)
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('只能设置一个最佳答案')));
        }
        $this->model('answer')->set_best_answer($_POST['answer_id'],$this->user_id,2);
        $this->model('question')->update('question',['best_answer'=>$_POST['answer_id']],'question_id='.$question_info['question_id']);
        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

    //更新通知状态
    public function read_notification_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(get_hook_info('system_message')['state'] == 1)
        {   
            hook('system_message','read_notification',array('notification_id'=>$_POST['notification_id']? :0,'uid'=>$this->user_id));

        }else
        {
            if (isset($_POST['notification_id']))
            {
                $this->model('notify')->read_notification($_POST['notification_id'], $this->user_id);
            }
            else
            {
                $this->model('notify')->mark_read_all($this->user_id);
            }
        }

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }


    public function send_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (trim($_POST['message']) == '')
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请输入私信内容')));
        }

        if (!$recipient_user = $this->model('account')->get_user_info_by_username($_POST['recipient']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('接收私信的用户不存在')));
        }

        if ($recipient_user['uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('不能给自己发私信')));
        }

        if ($recipient_user['inbox_recv'])
        {
            if (! $this->model('message')->check_permission($recipient_user['uid'], $this->user_id))
            {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对方设置了只有 Ta 关注的人才能给 Ta 发送私信')));
            }
        }

        $this->model('message')->send_message($this->user_id, $recipient_user['uid'], $_POST['message']);

        H::ajax_json_output(AWS_APP::RSM(null, 1, null));
    }

     /*
      删除私信
      xin
    */
    public function del_inbox_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(!$dialog_info = $this->model('message')->get_dialog_by_id(intval($_POST['dialog_id'])))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("私信不存在")));
        }

        if($dialog_info['recipient_uid'] != $this->user_id && $dialog_info['sender_uid'] != $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("该条私信不属于你")));
        }

        $this->model('message')->delete_dialog(intval($_POST['dialog_id']),$this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null,  1 , AWS_APP::lang()->_t('删除成功')));

    }


    /*
      保存用户设置
      xin
      $_POST['notification_settings'] 类型 [104 => 1,106 => 1,105 => 1] //选中的消息配置 其他将改为未选中
    */
    public function save_privacy_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $_POST['notification_settings'] = json_decode($_POST['notification_settings'],true);
        
        // $settings = objarray_to_array(json_decode($_POST['setting_str']));

        ksort($_POST['notification_settings']);

        if ($notify_actions = $this->model('notify')->notify_action_details)
        {   
            ksort($notify_actions);

            $notification_setting = array();
 
            foreach ($notify_actions as $key => $val)
            {   
                if (!isset($_POST['notification_settings'][$key]) AND $val['user_setting'])
                {   
                    $notification_setting[] = intval($key);
                }
            }
        }

        $this->model('account')->update_users_fields(array(
            'inbox_recv' => intval($_POST['inbox_recv'])
        ), $this->user_id);

        $this->model('account')->update_notification_setting_fields($notification_setting, $this->user_id);
        
        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('通知设置保存成功')));

    }
    
    //头像上传
    public function avatar_upload_action()
    {       
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

            if(get_hook_info('osd')['state']==1 and get_hook_config('osd')['group']['base']['config']['status']['value']!='no'){
                    $ret=hook('osd','upload_files',['cat'=>'avatar','field'=>'aws_upload_file']);
                    $this->model('account')->update('users',['avatar_file'=>$ret['pic']],"uid=".$this->user_id);
                    // echo htmlspecialchars(json_encode(array(
                    //     'success' => true,
                    //     'thumb' => $ret['pic'].'?x-oss-process=image/resize,m_fixed,h_100,w_100#'
                    // )), ENT_NOQUOTES);
                    H::ajax_json_output(AWS_APP::RSM(array(
                        'thumb' => $ret['pic'].'?x-oss-process=image/resize,m_fixed,h_100,w_100#',
                        'thumb_dir' => null,
                        'pic_name' => null
                    ), 1, null));
            }else{

                    AWS_APP::upload()->initialize(array(
                        'allowed_types' => 'jpg,jpeg,png,gif',
                        'upload_path' => get_setting('upload_dir') . '/avatar/' . $this->model('account')->get_avatar($this->user_id, '', 1),
                        'is_image' => TRUE,
                        'max_size' => get_setting('upload_avatar_size_limit'),
                        'file_name' => $this->model('account')->get_avatar($this->user_id, '', 2),
                        'encrypt_name' => FALSE
                    ))->do_upload('aws_upload_file');

                    if (AWS_APP::upload()->get_error())
                    {
                        switch (AWS_APP::upload()->get_error())
                        {
                            default:
                                die("{'error':'错误代码: " . AWS_APP::upload()->get_error() . "'}");
                            break;

                            case 'upload_invalid_filetype':
                                die("{'error':'文件类型无效'}");
                            break;

                            case 'upload_invalid_filesize':
                                die("{'error':'文件尺寸过大, 最大允许尺寸为 " . get_setting('upload_avatar_size_limit') .  " KB'}");
                            break;
                        }
                    }

                    if (! $upload_data = AWS_APP::upload()->data())
                    {
                        die("{'error':'上传失败, 请与管理员联系'}");
                    }

                    if ($upload_data['is_image'] == 1)
                    {
                        foreach(AWS_APP::config()->get('image')->avatar_thumbnail AS $key => $val)
                        {
                            $thumb_file[$key] = $upload_data['file_path'] . $this->model('account')->get_avatar($this->user_id, $key, 2);

                            AWS_APP::image()->initialize(array(
                                'quality' => 90,
                                'source_image' => $upload_data['full_path'],
                                'new_image' => $thumb_file[$key],
                                'width' => $val['w'],
                                'height' => $val['h']
                            ))->resize();
                        }
                    }

                    $update_data['avatar_file'] = $this->model('account')->get_avatar($this->user_id, null, 1) . basename($thumb_file['min']);

                    // 更新主表
                    $this->model('account')->update_users_fields($update_data, $this->user_id);

                    if (!$this->model('integral')->fetch_log($this->user_id, 'UPLOAD_AVATAR'))
                    {
                        $this->model('integral')->process($this->user_id, 'UPLOAD_AVATAR', round((get_setting('integral_system_config_profile') * 0.2)), '上传头像');
                    }

                    // echo htmlspecialchars(json_encode(array(
                    //     'success' => true,
                    //     'thumb' => get_setting('upload_url') . '/avatar/' . $this->model('account')->get_avatar($this->user_id, null, 1) . basename($thumb_file['max'])
                    // )), ENT_NOQUOTES);

                    H::ajax_json_output(AWS_APP::RSM(array(
                        'thumb' => get_setting('upload_url') . '/avatar/' . $this->model('account')->get_avatar($this->user_id, null, 1) . basename($thumb_file['max']),
                        'thumb_dir' => str_replace(base_url(),'', get_setting('upload_url')). '/avatar/' . $this->model('account')->get_avatar($this->user_id, null, 1) . basename($thumb_file['max']),
                        'pic_name' => $upload_data['file_name'],
                    ), 1, null));

                
            }
    }

    //专栏logo上传
    public function column_logo_upload_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(get_hook_info('osd')['state']==1 and get_hook_config('osd')['group']['base']['config']['status']['value']!='no'){
                $ret=hook('osd','upload_files',['cat'=>'column','field'=>'aws_upload_file']);

                H::ajax_json_output(AWS_APP::RSM(array(
                    'thumb' => $ret['pic'],
                    'thumb_dir' => null,
                    'pic_name' => null
                ), 1, null));

                // echo htmlspecialchars(json_encode(array(
                //     'success' => true,
                //     'thumb' => $ret['pic'],
                //     'thumb_dir' => null,
                //     'pic_name' => null
                // )), ENT_NOQUOTES);
            }else{

                $list = '/column';

                $path = get_setting('upload_dir') . $list;

                AWS_APP::upload()->initialize(array(
                    'allowed_types' => 'jpg,jpeg,png,gif',
                    'upload_path' => $path,
                    'is_image' => TRUE,
                    'max_size' => get_setting('upload_avatar_size_limit'),
                ))->do_upload('aws_upload_file');

                if (AWS_APP::upload()->get_error())
                {
                    switch (AWS_APP::upload()->get_error())
                    {
                        default:
                            die("{'error':'错误代码: " . AWS_APP::upload()->get_error() . "'}");
                            break;

                        case 'upload_invalid_filetype':
                            die("{'error':'文件类型无效'}");
                            break;

                        case 'upload_invalid_filesize':
                            die("{'error':'文件尺寸过大, 最大允许尺寸为 " . get_setting('upload_avatar_size_limit') .  " KB'}");
                            break;
                        case 'upload_file_exceeds_limit':
                            die("{'error':'文件尺寸超出服务器限制'}");
                            break;
                    }
                }

                if (! $upload_data = AWS_APP::upload()->data())
                {
                    die("{'error':'上传失败, 请与管理员联系'}");
                }

                H::ajax_json_output(AWS_APP::RSM(array(
                    'thumb' => get_setting('upload_url') . $list .'/'.$upload_data['file_name'],
                    'thumb_dir' => str_replace(base_url(),'', get_setting('upload_url')).$list .'/'.$upload_data['file_name'],
                    'pic_name' => $upload_data['file_name'],
                ), 1, null));


                // echo htmlspecialchars(json_encode(array(
                //     'success' => true,
                //     'thumb' => get_setting('upload_url') . $list .'/'.$upload_data['file_name'],
                //     'thumb_dir' => str_replace(base_url(),'', get_setting('upload_url')).$list .'/'.$upload_data['file_name'],
                //     'pic_name' => $upload_data['file_name'],
                // )), ENT_NOQUOTES);
            }
    }
    
    //文章封面
    public function article_logo_upload_action()
    {
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(get_hook_info('osd')['state']==1 and get_hook_config('osd')['group']['base']['config']['status']['value']!='no'){
                $ret=hook('osd','upload_files',['cat'=>'article','field'=>'aws_upload_file']);
                // if(get_hook_config('osd')['group']['base']['config']['status']['value']=='oss')
                //     $img= $ret['pic'];
                // if(get_hook_config('osd')['group']['base']['config']['status']['value']=='cos')
                //     $img= $ret['data'][0];
                H::ajax_json_output(AWS_APP::RSM(array(
                    'thumb' => $ret['pic'],
                    'thumb_dir' => null,
                    'pic_name' => null
                ), 1, null));
            }else{

                $list = '/article_logo/' . gmdate('Ymd');

                $path = get_setting('upload_dir') . $list;

                AWS_APP::upload()->initialize(array(
                    'allowed_types' => 'jpg,jpeg,png,gif',
                    'upload_path' => $path,
                    'is_image' => TRUE,
                    'max_size' => get_setting('upload_avatar_size_limit'),
                ))->do_upload('aws_upload_file');

                if (AWS_APP::upload()->get_error()) {
                    switch (AWS_APP::upload()->get_error()) {
                        default:
                            die("{'error':'错误代码: " . AWS_APP::upload()->get_error() . "'}");
                            break;

                        case 'upload_invalid_filetype':
                            die("{'error':'文件类型无效'}");
                            break;

                        case 'upload_invalid_filesize':
                            die("{'error':'文件尺寸过大, 最大允许尺寸为 " . get_setting('upload_avatar_size_limit') . " KB'}");
                            break;
                        case 'upload_file_exceeds_limit':
                            die("{'error':'文件尺寸超出服务器限制'}");
                            break;
                    }
                }
                if (!$upload_data = AWS_APP::upload()->data()) {
                    die("{'error':'上传失败, 请与管理员联系'}");
                }

                 H::ajax_json_output(AWS_APP::RSM(array(
                    'thumb' => get_setting('upload_url') . $list .'/'.$upload_data['file_name'],
                    'thumb_dir' => str_replace(base_url(),'', get_setting('upload_url')).$list .'/'.$upload_data['file_name'],
                    'pic_name' => $upload_data['file_name'],
                ), 1, null));

                
            }
    }

    //问题 回复 文章图片上传
    public function img_upload_action()
    {   
        
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        switch (trim($_POST['type'])){
            case 'question'://问题
                $list = '/question/'. gmdate('Ymd');
                break;
            case 'article'://贴子
                $list = '/article/'. gmdate('Ymd');
                break;
            case 'answer'://问题回复
                $list = '/answer/'. gmdate('Ymd');
                break;
        }

        $path = get_setting('upload_dir') . $list;

        if(!$list)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('参数错误')));
        }

        if(get_hook_info('osd')['state']==1 and get_hook_config('osd')['group']['base']['config']['status']['value']!='no'){
                $ret=hook('osd','upload_files',['cat'=>$_POST['type'],'field'=>'aws_upload_file']);
                H::ajax_json_output(AWS_APP::RSM(array(
                    'thumb' => $ret['pic'],
                    'thumb_dir' => null,
                    'pic_name' => null
                ), 1, null));
            }else{
                AWS_APP::upload()->initialize(array(
                    'allowed_types' => 'jpg,jpeg,png,gif',
                    'upload_path' => $path,
                    'is_image' => TRUE,
                    'max_size' => get_setting('upload_avatar_size_limit'),
                ))->do_upload('aws_upload_file');

                if (AWS_APP::upload()->get_error())
                {
                    switch (AWS_APP::upload()->get_error())
                    {
                        default:
                            die("{'error':'错误代码: " . AWS_APP::upload()->get_error() . "'}");
                            break;

                        case 'upload_invalid_filetype':
                            die("{'error':'文件类型无效'}");
                            break;

                        case 'upload_invalid_filesize':
                            die("{'error':'文件尺寸过大, 最大允许尺寸为 " . get_setting('upload_avatar_size_limit') .  " KB'}");
                            break;
                        case 'upload_file_exceeds_limit':
                            die("{'error':'文件尺寸超出服务器限制'}");
                            break;
                    }
                }

                if (! $upload_data = AWS_APP::upload()->data())
                {
                    die("{'error':'上传失败, 请与管理员联系'}");
                }

                H::ajax_json_output(AWS_APP::RSM(array(
                    'thumb' => get_setting('upload_url') . $list .'/'.$upload_data['file_name'],
                    'thumb_dir' => str_replace(base_url(),'', get_setting('upload_url')).$list .'/'.$upload_data['file_name'],
                    'pic_name' => $upload_data['file_name'],
                ), 1, null));
            }
    }

    public function apply_column_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['publish_column'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('你所在用户组没有权限申请专栏')));
        }
        if (!trim($_POST['logo_img'])) {
            H::ajax_json_output(AWS_APP::RSM(null,-1,AWS_APP::lang()->_t('专栏图片必须上传')));
        }
        $column_name = $_POST['name'];
        $column_description = $_POST['description'];
        $column_pic = $_POST['logo_img'];

        if (!$column_name) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请输入专栏名称")));
        }
        if (!$column_description) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请输入专栏简介")));
        }
        if (cjk_strlen($column_description) > 60) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("专栏简介字数不得超过60")));
        }
        if (get_setting('upload_enable') == 'Y' AND get_setting('advanced_editor_enable' == 'Y') AND !$column_pic) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请上传专栏封面")));
        }
        

        $column_id=$this->model('column')->apply_column($column_name, $column_description, $column_pic, $this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('申请成功')));
    }
    public function edit_column_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$column_info = $this->model('column')->get_column_by_id($_POST['id'])){
            H::ajax_json_output(AWS_APP::RSM(null,-1,AWS_APP::lang()->_t('指定专栏不存在')));
        }
        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'] and !$this->user_info['permission']['edit_column'] and $column_info['uid']!=$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null,-1,AWS_APP::lang()->_t('你没有权限编辑这个专栏')));
        }

        if ($column_info['is_verify'] == 0) {
            H::ajax_json_output(AWS_APP::RSM(null,-1,AWS_APP::lang()->_t('审核中的专栏无法编辑')));
        }

        $column_name = $_POST['name'];
        $column_description = $_POST['description'];
        $column_pic = $_POST['logo_img'];

        if (!trim($_POST['logo_img'])) {
            H::ajax_json_output(AWS_APP::RSM(null,-1,AWS_APP::lang()->_t('专栏图片必须上传')));
        }

        if (!$column_name) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请输入专栏名称")));
        }

        if (!$column_description) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请输入专栏简介")));
        }

        if (cjk_strlen($column_description) > 60) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("专栏简介字数不得超过60")));
        }

        if (get_setting('upload_enable') == 'Y' AND get_setting('advanced_editor_enable' == 'Y') AND!$column_pic) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t("请上传专栏封面")));
        }

        $this->model('column')->edit_apply_column($column_info['column_id'],$column_name, $column_description, $column_pic);

        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('编辑成功')));
    }

    public function search_focus_topics_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $key = $_GET['keyword'];
        $page = intval($_GET['page']);
        if ($key)
            $where = "topic_title like '%" . $key . "%' ";
        else
            $where = '1=1';
        $data['code']=1;
        $data = $this->model('topic')->get_focus_topic_by_js($this->user_id, '5', $where,$page);
       
        if($data)
        H::ajax_json_output(AWS_APP::RSM(array(
                'list' => $data,
            ), 1, null));
        else
        H::ajax_json_output(AWS_APP::RSM(null, -1, '暂无内容'));
    }


    public function logout_action($return_url = null)
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $this->model('account')->logout();

        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('您已退出站点, 现在将以游客身份进入站点, 请稍候...')));

    }

    //个人中心 参与 发起
    public function user_actions_action()
    {   

        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if ((isset($_GET['perpage']) AND intval($_GET['perpage']) > 0))
        {
            $this->per_page = intval($_GET['perpage']);
        }

        $data = $this->model('actions')->get_user_actions($_GET['uid'], (intval($_GET['page']) * $this->per_page) . ", {$this->per_page}", $_GET['actions'], $this->user_id);

        $data = array_values($data);

        H::ajax_json_output(AWS_APP::RSM(array('list'=>$data,'page'=>intval($_GET['page'])), 1, null));
       
    }
    
    //收藏列表
    public function favorite_list_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if ($action_list = $this->model('favorite')->get_item_list($_GET['tag'], $this->user_id, calc_page_limit($_GET['page'], get_setting('contents_per_page'))))
        {
            H::ajax_json_output(AWS_APP::RSM(array('result'=>$action_list), 1, null));
        }
        else
        {
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('暂无更多')));
        }
    }

    public function get_user_info_action($username,$password){
        if(preg_match("/^1[345789]\d{9}$/", $username)){
            $user_info = $this->model('account')->login_check_mobile($username, $password);
        }else{
            $user_info = $this->model('account')->check_user_name($username, $password);
        }
        return $user_info;
    }

    //修改用户资料
    public function profile_setting_action()
    {
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $update_data['sex'] = intval($_POST['sex']);

        if (!$this->user_info['verified'])
        {
            $update_attrib_data['signature'] = htmlspecialchars($_POST['signature']);
        }

        if ($_POST['signature'] AND !$this->model('integral')->fetch_log($this->user_id, 'UPDATE_SIGNATURE'))
        {
            $this->model('integral')->process($this->user_id, 'UPDATE_SIGNATURE', round((get_setting('integral_system_config_profile') * 0.1)), AWS_APP::lang()->_t('完善一句话介绍'));
        }

        // if($_POST['mobile']){
        //     if(!preg_match("/^1[345789]\d{9}$/", $_POST['mobile']))
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('手机号码格式不正确')));
        //     $update_data['mobile'] = htmlspecialchars($_POST['mobile']);
        // }

        // 更新主表
        $this->model('account')->update_users_fields($update_data, $this->user_id);

        // 更新从表
        $this->model('account')->update_users_attrib_fields($update_attrib_data, $this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('个人资料保存成功')));
    }

     /*
      保存草稿
      xin
    */
    public function save_draft_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(!$_POST['type'] || ($_POST['type'] != 'article' AND $_POST['type'] != 'answer' AND $_POST['type'] != 'question'))
        {
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('类型错误')));
        }

        if($_POST['type'] == 'answer' && !$_POST['item_id'])
        {   
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('该类型需选择关联id')));
        }

        $_POST['message'] = $_POST['message'];

        if (!trim($_POST['message']))
        {   
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('草稿内容不得为空')));
        }

        if($_POST['type'] == 'article' || $_POST['type'] == 'question')
        {
            $_POST['item_id'] = 0;
        }

        $this->model('draft')->save_draft(intval($_POST['item_id']), trim($_POST['type']), $this->user_id, $_POST);

        H::ajax_json_output(AWS_APP::RSM(null,1,AWS_APP::lang()->_t('保存成功')));

    }

    /*
     删除草稿
     xin
    */
    
    public function del_draft_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if(!$_POST['type'] || ($_POST['type'] != 'article' AND $_POST['type'] != 'answer' AND $_POST['type'] != 'question'))
        {
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('类型错误')));
        }

        if($_POST['type'] == 'answer' && !$_POST['item_id'])
        {   
            H::ajax_json_output(AWS_APP::RSM(array(),-1, AWS_APP::lang()->_t('该类型需选择关联id')));
        }

        if($_POST['type'] == 'article' || $_POST['type'] == 'question')
        {
            $_POST['item_id'] = 0;
        }

        $this->model('draft')->delete_draft(intval($_POST['item_id']), trim($_POST['type']), $this->user_id);
        
        H::ajax_json_output(AWS_APP::RSM(null,1,AWS_APP::lang()->_t('删除成功')));

    }


    public function weixin_login_process_action()
    {
        $exp = 60 * 60 * 24;

        $code = trim($_POST['code']);

        $encryptedData = trim($_POST['encryptedData']);
        
        // file_put_contents('./log3.txt',var_export($_POST,true)."\n",FILE_APPEND);

        $iv = trim($_POST['iv']);

        if (!$code) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, '参数异常，请稍后重试'));
        }

        $appid = get_setting('weixin_xcx_app_id');

        $secret = get_setting('weixin_xcx_app_secret');

        if(!$appid || !$secret)
        {
           H::ajax_json_output(AWS_APP::RSM(null, -1, '小程序未配置,无法登录'));
        }

        $session_key = AWS_APP::cache()->get($code);

        if(!$session_key){

            $res = $this->model('api')->get_sessionKey($appid, $secret, $code);

            if (!$res['session_key']) {
                H::ajax_json_output(AWS_APP::RSM(null, -1, '小程序session_key异常，请稍后重试'));
            }

            $session_key = $res['session_key'];
        }

        $return_data['session_key'] = $session_key;

        AWS_APP::cache()->set($code, $session_key, $exp);

        $pc = new WXBizDataCrypt($appid, $session_key);

        $errCode = $pc->decryptData($encryptedData, $iv, $data);//解密微信数据

        file_put_contents('./log4.txt',var_export($errCode,true)."\n",FILE_APPEND);
        
        if ($errCode == 0) {

            $openId = $data['openId'];
            
            if ($weixin_user = $this->model('api')->get_user_info_by_min_openid($openId)) {

                $uid = $weixin_user['uid'];

                $user_info = $this->model('account')->get_user_info_by_uid($uid);

//                $key = $this->model('api')->setcookie_login($user_info['uid'], $user_info['user_name'], $user_info['password'], $user_info['salt'], null, false);
                $key = md5($user_info['uid'].time());
                AWS_APP::cache()->set($key,$user_info['uid'],60 * 60 * 24);

                $user_info['avatar_file'] = get_avatar_url($user_info['uid'],'max');

                H::ajax_json_output(AWS_APP::RSM(array(
                    'key' => $key,
                    'user'=> $user_info,
                    'wechat' => $key
                ), 1, AWS_APP::lang()->_t('登录成功')));


            } else {

                H::ajax_json_output(AWS_APP::RSM($data, -4, AWS_APP::lang()->_t('登录失败,小程序未绑定wecenter')));
            }

        } else {
            
            H::ajax_json_output(AWS_APP::RSM(null, -1, '数据异常，请稍后重试'));
        }
    }


    //QQ 微博 微信接触绑定
    public function unbind_app_action()
    {       

            if(!$this->user_id)
            {
                H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
            }

            if($_POST['type'] == 'weibo')
            {
                // //绑定微博
                // if(!$_POST['weiboUser']['id'])
                // {
                //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('参数不正确')));
                // }
                // if(!$weiboUser = $this->model('openid_weibo_oauth')->get_weibo_user_by_uid($this->user_id))
                // {
                //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微博账号没有绑定')));
                // }

                $this->model('openid_weibo_oauth')->unbind_account($this->user_id);


            }else if($_POST['type'] == 'weixin')
            {
                
                // if(!$_POST['weixinUser']['nickname'])
                // {
                //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('参数不正确')));
                // }
                // if(!$weixinUser = $this->model('openid_weixin_weixin')->get_user_info_by_openid($_POST['weixinUser']['openid']))
                // {
                //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('微信账号没有绑定')));
                // }

                $this->model('openid_weixin_weixin')->weixin_unbind($this->user_id);


            }else if($_POST['type'] == 'qq')
            {
                
                // if(!$_POST['qqUser']['nickname'])
                // {
                //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('参数不正确')));
                // }
                // if(!$qqUser = $this->model('openid_qq')->get_qq_user_by_openid($_POST['qqUser']['openid']))
                // {
                //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('QQ账号没有绑定')));
                // }

                $this->model('openid_qq')->unbind_account($this->user_id);

            }

            // $this->model('account')->logout();

            H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('解除成功')));
    }
   

   //修改密码
    public function find_password_modify_action()
    {   
       
        if (!$_POST['password'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('请输入密码')));
        }
        if (strlen($_POST['password']) < 6 OR strlen($_POST['password']) > 16) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入6-16位的新密码')));
        }
        
        /*if (!trim($_POST['seccode_verify']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1',  AWS_APP::lang()->_t('验证码不能为空')));
        }       if (!AWS_APP::captcha()->is_validate($_POST['seccode_verify']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('请填写正确的验证码')));
        }*/
        // if(!$this->model('tools')->geetest($_POST)){
        //     H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('验证码错误')));
        // }
        $active_data = $this->model('active')->get_active_code($_POST['active_code'], 'FIND_PASSWORD');

        if ($active_data)
        {
            if ($active_data['active_time'] OR $active_data['active_ip'])
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('链接已失效，请重新找回密码')));
            }
        }
        else
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('链接已失效，请重新找回密码')));
        }


        if (! $uid = $this->model('active')->active_code_active($_POST['active_code'], 'FIND_PASSWORD'))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('链接已失效，请重新找回密码')));
        }

        $user_info = $this->model('account')->get_user_info_by_uid($uid);

        $this->model('account')->update_user_password_ingore_oldpassword($_POST['password'], $uid, $user_info['salt']);

        $this->model('active')->set_user_email_valid_by_uid($user_info['uid']);

        if ($user_info['group_id'] == 3)
        {
            $this->model('active')->active_user_by_uid($user_info['uid']);
        }

        $this->model('account')->logout();

        unset(AWS_APP::session()->find_password);

        H::ajax_json_output(AWS_APP::RSM(null, 1,  AWS_APP::lang()->_t('密码修改成功, 请返回登录')));
    }
    //邮箱找回密码
    public function find_password_email_action()
    {   


        if ($this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('已登录')));
        }

        if (!H::valid_email($_POST['email']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('请填写正确的邮箱地址')));
        }
        // if (!trim($_POST['seccode_verify']))
        // {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1',  AWS_APP::lang()->_t('验证码不能为空')));
        // }
        // if (!AWS_APP::captcha()->is_validate($_POST['seccode_verify']))
        // {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1',  AWS_APP::lang()->_t('请填写正确的验证码')));
        // }

        if (!$user_info = $this->model('account')->get_user_info_by_email($_POST['email']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('邮箱地址错误或帐号不存在')));
        }

        $this->model('active')->new_find_password($user_info['uid']);

        AWS_APP::session()->find_password = $user_info['email'];

        H::ajax_json_output(AWS_APP::RSM(array(
            'email' => $user_info['email']
        ), 1, AWS_APP::lang()->_t('密码重置链接已经发到您邮箱')));
    }
    
    //手机号找回密码
    public function first_find_password_action()
    {   
        if ($this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('已登录')));
        }
        
        hook('mobile_regist','find_password',array('mobile'=>$_POST['mobile'],'smscode'=>$_POST['smscode']));
        
        if (!$user_info = $this->model('account')->get_user_info_by_mobile($_POST['mobile']))
        {   
            H::ajax_json_output(AWS_APP::RSM(null, -1,  AWS_APP::lang()->_t('帐号不存在')));
        }
        
        $key=$this->model('active')->new_find_password($user_info['uid'],'master','mobile');

        H::ajax_json_output(AWS_APP::RSM(array(
            'key' => $key
        ), 1, null));
    }

    public function bind_mobile_one_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if($_POST['old_mobile'])
        {
             if(!preg_match("/^1[345789]\d{9}$/", $_POST['old_mobile']))
             {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('手机号码格式不正确')));
                
             }

             if(!$user_info = $this->model('account')->get_user_info_by_uid($this->user_id))
             {
                H::ajax_json_output(AWS_APP::RSM(null, '-3', AWS_APP::lang()->_t('请先登录')));
             }
             //如果该用户绑定了手机号  查询绑定的号码是否与old_mobile一致 如果不一致 提示报错

             if($user_info['mobile'] != $_POST['old_mobile'])
             {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('账号绑定手机与旧手机号不一致')));
             }
             //验证老手机号验证码
             $this->model('tools')->checkSmsCode($_POST['old_mobile'],$_POST['old_smscode']);

             $post_hash = md5($this->user_id);

             AWS_APP::cache()->set('user'.$this->user_id,$post_hash,300);
 
             H::ajax_json_output(AWS_APP::RSM(array(
                'post_hash' => $post_hash
             ), 1, null));

        }else
        {
             H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入手机号')));
        }
         
    }


    public function bind_mobile_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if ($_POST['mobile'] == '') {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入手机号')));
        }

        if($_POST['mobile']){
            if(!preg_match("/^1[345789]\d{9}$/", $_POST['mobile']))
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('手机号码格式不正确')));
        }

        if ($this->model('account')->check_mobile($_POST['mobile'])) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('手机号已存在')));
        }

        if ($_POST['smscode'] == '') {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入短信验证码')));
        }
        if (!is_numeric($_POST['smscode'])) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入正确格式的短信验证码')));
        }

        // !注: 来路检测后面不能再放报错提示

        if($_POST['post_hash'] && !$post_hash = AWS_APP::cache()->get('user'.$this->user_id))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
        }

        $this->model('tools')->checkSmsCode($_POST['mobile'],$_POST['smscode']);

        $update_data['mobile'] = htmlspecialchars($_POST['mobile']);
        // 更新主表
        $this->model('account')->update_users_fields($update_data, $this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('绑定成功')));
    }
    
    //发起问题
    public function publish_question_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['publish_question']) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限发布问题')));
        }

        if ($this->user_info['integral'] < 0 AND get_setting('integral_system_enabled') == 'Y') {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余积分已经不足以进行此操作')));
        }

        if (!$_POST['question_content']) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入问题标题')));
        }

        if (get_setting('category_enable') == 'N') {
            $_POST['category_id'] = 1;
        }

        if (!$_POST['category_id']) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择问题分类')));
        }

        if (cjk_strlen($_POST['question_content']) < 5) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题标题字数不得少于 5 个字')));
        }

        if (get_setting('question_title_limit') > 0 AND cjk_strlen($_POST['question_content']) > get_setting('question_title_limit')) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题标题字数不得大于 %s 字节', get_setting('question_title_limit'))));
        }

        if (!$this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($_POST['question_detail'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        /*if (human_valid('question_valid_hour') AND !AWS_APP::captcha()->is_validate($_POST['seccode_verify'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请填写正确的验证码')));
        }*/
        // if (!is_mobile() && human_valid('question_valid_hour') AND !$this->model('tools')->geetest($_POST)) {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('验证码错误')));
        // }
        
        $_POST['topics'] = explode(',', $_POST['topics']);

        if ($_POST['topics']) {
            foreach ($_POST['topics'] AS $key => $topic_title) {
                $topic_title = trim($topic_title);

                if (!$topic_title) {
                    unset($_POST['topics'][$key]);
                } else {
                    $_POST['topics'][$key] = $topic_title;
                }
            }

            if (get_setting('question_topics_limit') AND sizeof($_POST['topics']) > get_setting('question_topics_limit')) {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('单个问题话题数量最多为 %s 个, 请调整话题数量', get_setting('question_topics_limit'))));
            }
        }

        if (!$_POST['topics'] AND get_setting('new_question_force_add_topic') == 'Y') {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请为问题添加话题')));
        }

        if (!$this->model('publish')->insert_attach_is_self_upload($_POST['question_detail'], $_POST['attach_ids'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('只允许插入当前页面上传的附件')));
        }

        // // !注: 来路检测后面不能再放报错提示
        // if (!valid_post_hash($_POST['post_hash'])) {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('页面停留时间过长,或内容已提交,请刷新页面')));
        // }

        $this->model('draft')->delete_draft(1, 'question', $this->user_id);

        if ($this->publish_approval_valid(array(
            $_POST['question_content'],
            $_POST['question_detail']
        )) and !$_POST['is_pay']) {
            $this->model('publish')->publish_approval('question', array(
                'question_content' => $_POST['question_content'],
                'question_detail' => $_POST['question_detail'],
                'category_id' => $_POST['category_id'],
                'topics' => $_POST['topics'],
                'anonymous' => $_POST['anonymous'],
                'ask_user_id' => $_POST['ask_user_id'],
                'permission_create_topic' => $this->user_info['permission']['create_topic']
            ), $this->user_id);

            H::ajax_json_output(AWS_APP::RSM(null, 2, '等待审核'));

        } else {

            $is_del=$_POST['is_pay']?1:0;

            $question_id = $this->model('publish')->publish_question($_POST['question_content'], $_POST['question_detail'], $_POST['category_id'], $this->user_id, $_POST['topics'], $_POST['anonymous'], null, $_POST['ask_user_id'], $this->user_info['permission']['create_topic'],null,$is_del);
            
            H::ajax_json_output(AWS_APP::RSM(array(
                    'question_id' => $question_id
                ), 1, null));
        }
    }


    public function modify_question_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$question_info = $this->model('question')->get_question_info_by_id($_POST['question_id'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
        }

        if ($question_info['lock'] AND !($this->user_info['permission']['is_administortar'] OR $this->user_info['permission']['is_moderator'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题已锁定, 不能编辑')));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'] AND !$this->user_info['permission']['edit_question']) {
            if ($question_info['published_uid'] != $this->user_id) {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限编辑这个问题')));
            }
        }

        if (!$_POST['category_id'] AND get_setting('category_enable') == 'Y') {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择分类')));
        }

        if (cjk_strlen(trim($_POST['question_content'])) < 5) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题标题字数不得少于 5 个字')));
        }

        if (get_setting('question_title_limit') > 0 AND cjk_strlen(trim($_POST['question_content'])) > get_setting('question_title_limit')) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题标题字数不得大于') . ' ' . get_setting('question_title_limit') . ' ' . AWS_APP::lang()->_t('字节')));
        }

        if (!$this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($_POST['question_detail'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        if (!$this->model('publish')->insert_attach_is_self_upload($_POST['question_detail'], $_POST['attach_ids'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('只允许插入当前页面上传的附件')));
        }

        /*if (human_valid('question_valid_hour') AND !AWS_APP::captcha()->is_validate($_POST['seccode_verify'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请填写正确的验证码')));
        }*/
        // if (!is_mobile() && human_valid('question_valid_hour') AND !$this->model('tools')->geetest($_POST)) {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('验证码错误')));
        // }
       

        $item_id = $_POST['question_id']?$_POST['question_id']:0;
        $this->model('draft')->delete_draft($item_id, 'question', $this->user_id);

        if ($_POST['do_delete'] AND !$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator']) {
            H::ajax_json_output(ANDWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有删除问题的权限')));
        }

        if ($_POST['do_delete']) {
            if ($this->user_id != $question_info['published_uid']) {
                $this->model('account')->send_delete_message($question_info['published_uid'], $question_info['question_content'], $question_info['question_detail']);
            }

            $this->model('question')->remove_question($question_info['question_id']);

            H::ajax_json_output(AWS_APP::RSM(null, 2, '已删除'));
        }

        $IS_MODIFY_VERIFIED = TRUE;

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'] AND $question_info['published_uid'] != $this->user_id) {
            $IS_MODIFY_VERIFIED = FALSE;
        }

        $this->model('question')->update_question($question_info['question_id'], trim($_POST['question_content']), $_POST['question_detail'], $this->user_id, $IS_MODIFY_VERIFIED, $_POST['modify_reason'], $question_info['anonymous'], $_POST['category_id']);

        if ($this->user_id != $question_info['published_uid']) {
            $this->model('question')->add_focus_question($question_info['question_id'], $this->user_id);

            $this->model('notify')->send($this->user_id, $question_info['published_uid'], notify_class::TYPE_MOD_QUESTION, notify_class::CATEGORY_QUESTION, $question_info['question_id'], array(
                'from_uid' => $this->user_id,
                'question_id' => $question_info['question_id']
            ));

            $this->model('email')->action_email('QUESTION_MOD', $question_info['published_uid'], get_js_url('/question/' . $question_info['question_id']), array(
                'user_name' => $this->user_info['user_name'],
                'question_title' => $question_info['question_content']
            ));
        }

        if ($_POST['category_id'] AND $_POST['category_id'] != $question_info['category_id']) {
            $category_info = $this->model('system')->get_category_info($_POST['category_id']);

            ACTION_LOG::save_action($this->user_id, $question_info['question_id'], ACTION_LOG::CATEGORY_QUESTION, ACTION_LOG::MOD_QUESTION_CATEGORY, $category_info['title'], $category_info['id']);
        }


        H::ajax_json_output(AWS_APP::RSM(array(
            'question_id' => $question_info['question_id']
        ), 1, null));

    }


    public function save_answer_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if ($this->user_info['integral'] < 0 and get_setting('integral_system_enabled') == 'Y')
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你的剩余积分已经不足以进行此操作')));
        }

        if (!$question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('问题不存在')));
        }

        if ($question_info['lock'] AND ! ($this->user_info['permission']['is_administortar'] OR $this->user_info['permission']['is_moderator']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('已经锁定的问题不能回复')));
        }

        $answer_content = trim($_POST['answer_content'], "\r\n\t ");
        $answer_content = preg_replace("/[\s\v".chr(194).chr(160)."]+$/","",$answer_content);
        $answer_content = htmlspecialchars($answer_content);
        if (! $answer_content)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请输入回复内容')));
        }

        // 判断是否是问题发起者
        if (get_setting('answer_self_question') == 'N' and $question_info['published_uid'] == $this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('不能回复自己发布的问题，你可以修改问题内容')));
        }

        // 判断是否已回复过问题
        if ((get_setting('answer_unique') == 'Y') AND $this->model('answer')->has_answer_by_uid($question_info['question_id'], $this->user_id))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('一个问题只能回复一次，你可以编辑回复过的回复')));
        }

        if (strlen($answer_content) < get_setting('answer_length_lower'))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('回复内容字数不得少于 %s 字节', get_setting('answer_length_lower'))));
        }

        if (! $this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($answer_content))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        if (!$this->model('publish')->insert_attach_is_self_upload($answer_content, $_POST['attach_ids']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('只允许插入当前页面上传的附件')));
        }

        $this->model('draft')->delete_draft($question_info['question_id'], 'answer', $this->user_id);

        if ($this->publish_approval_valid($answer_content))
        {
            $this->model('publish')->publish_approval('answer', array(
                'question_id' => $question_info['question_id'],
                'answer_content' => $answer_content,
                'anonymous' => $_POST['anonymous'],
                'auto_focus' => $_POST['auto_focus']
            ), $this->user_id);

            H::ajax_json_output(AWS_APP::RSM(null, 1, '回复成功请等待审核'));
        }
        else
        {
            $answer_id = $this->model('publish')->publish_answer($question_info['question_id'], $answer_content, $this->user_id, $_POST['anonymous'], $_POST['attach_access_key'], $_POST['auto_focus']);


            $answer_info = $this->model('answer')->get_answer_by_id($answer_id);

            $answer_info['user_info'] = $this->user_info;
            $answer_info['answer_content'] = html_entity_decode($this->model('question')->parse_at_user(FORMAT::parse_attachs(nl2br(FORMAT::parse_bbcode($answer_info['answer_content'])))));

            
            H::ajax_json_output(AWS_APP::RSM(null, 1, '回复成功'));


        }
    }


    public function update_answer_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (! $answer_info = $this->model('answer')->get_answer_by_id($_GET['answer_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('答案不存在')));
        }

        if ($_POST['do_delete'])
        {
            if ($answer_info['uid'] != $this->user_id and ! $this->user_info['permission']['is_administortar'] and ! $this->user_info['permission']['is_moderator'])
            {
                H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('你没有权限进行此操作')));
            }

            $this->model('answer')->remove_answer_by_id($_GET['answer_id']);

            // 通知回复的作者
            if ($this->user_id != $answer_info['uid'])
            {
                $this->model('notify')->send($this->user_id, $answer_info['uid'], notify_class::TYPE_REMOVE_ANSWER, notify_class::CATEGORY_QUESTION, $answer_info['question_id'], array(
                    'from_uid' => $this->user_id,
                    'question_id' => $answer_info['question_id']
                ));
            }

            $this->model('question')->save_last_answer($answer_info['question_id']);

            H::ajax_json_output(AWS_APP::RSM(array('url'=>''), 1, null));

        }

        $answer_content = trim($_POST['answer_content'], "\r\n\t");

        if (!$answer_content)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请输入回复内容')));
        }

        if (strlen($answer_content) < get_setting('answer_length_lower'))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('回复内容字数不得少于 %s 字节', get_setting('answer_length_lower'))));
        }

        if (! $this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($answer_content))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        if ($answer_info['uid'] != $this->user_id and ! $this->user_info['permission']['is_administortar'] and ! $this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限编辑这个回复')));
        }

        if ($answer_info['uid'] == $this->user_id and (time() - $answer_info['add_time'] > get_setting('answer_edit_time') * 60) and get_setting('answer_edit_time') and ! $this->user_info['permission']['is_administortar'] and ! $this->user_info['permission']['is_moderator'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('已经超过允许编辑的时限')));
        }

        $this->model('answer')->update_answer($_GET['answer_id'], $answer_info['question_id'], $answer_content);

        H::ajax_json_output(AWS_APP::RSM(null, 1, '编辑成功'));
    }
    

    public function save_question_comment_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$_POST['question_id'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('问题不存在')));
        }

        if (!$this->user_info['permission']['publish_comment'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有发表评论的权限')));
        }

        if (trim($_POST['message']) == '')
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入评论内容')));
        }

        $question_info = $this->model('question')->get_question_info_by_id($_POST['question_id']);

        if ($question_info['lock'] AND ! ($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('不能评论锁定的问题')));
        }

        if (get_setting('comment_limit') > 0 AND (cjk_strlen($_POST['message']) > get_setting('comment_limit')))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('评论内容字数不得超过 %s 字节', get_setting('comment_limit'))));
        }

        $this->model('question')->insert_question_comment($_POST['question_id'], $this->user_id, $_POST['message']);

        H::ajax_json_output(AWS_APP::RSM(array(
            'item_id' => intval($_POST['question_id']),
            'type_name' => 'question'
        ), 1, null));
    }
    

    public function save_answer_comment_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (! $_POST['answer_id'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('回复不存在')));
        }

        if (!$this->user_info['permission']['publish_comment'])
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有发表评论的权限')));
        }
        $message = trim($_POST['message'], "\r\n\t ");
        if ($message == '')
        {
            H::ajax_json_output(AWS_APP::RSM(null, - 1, AWS_APP::lang()->_t('请输入评论内容')));
        }

        if (get_setting('comment_limit') > 0 AND cjk_strlen($_POST['message']) > get_setting('comment_limit'))
        {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('评论内容字数不得超过 %s 字节', get_setting('comment_limit'))));
        }

        $answer_info = $this->model('answer')->get_answer_by_id($_POST['answer_id']);
        $question_info = $this->model('question')->get_question_info_by_id($answer_info['question_id']);

        if ($question_info['lock'] AND ! ($this->user_info['permission']['is_administortar'] or $this->user_info['permission']['is_moderator']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('不能评论锁定的问题')));
        }

        if (! $this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($_POST['message']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        $this->model('answer')->insert_answer_comment($_POST['answer_id'], $this->user_id, $_POST['message']);

        H::ajax_json_output(AWS_APP::RSM(array(
            'item_id' => intval($_POST['answer_id']),
            'type_name' => 'answer'
        ), 1, null));
    }

    public function save_article_comment_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$article_info = $this->model('article')->get_article_info_by_id($_POST['article_id']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('指定文章不存在')));
        }

        if ($article_info['lock'] AND !($this->user_info['permission']['is_administortar'] OR $this->user_info['permission']['is_moderator']))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('已经锁定的文章不能回复')));
        }

        $message = trim($_POST['message'], "\r\n\t ");
        if (! $message)
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请输入回复内容')));
        }

        if (strlen($message) < get_setting('answer_length_lower'))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('回复内容字数不得少于 %s 字节', get_setting('answer_length_lower'))));
        }

        if (! $this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($message))
        {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        if ($this->publish_approval_valid($message))
        {
            $this->model('publish')->publish_approval('article_comment', array(
                'article_id' => intval($_POST['article_id']),
                'message' => $message,
                'at_uid' => intval($_POST['at_uid'])
            ), $this->user_id);

            H::ajax_json_output(AWS_APP::RSM(null, 2, '等待后台审核'));
        }
        else
        {
            $comment_id = $this->model('publish')->publish_article_comment($_POST['article_id'], $message, $this->user_id, $_POST['at_uid']);

            //$url = get_js_url('/article/' . intval($_POST['article_id']) . '?item_id=' . $comment_id);

            H::ajax_json_output(AWS_APP::RSM(array(
                'item_id' => intval($_POST['article_id']),
                'type_name' => 'article'
            ), 1, null));
            
        }
    }

    public function publish_article_action()
    {   
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$this->user_info['permission']['publish_article']) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限发布文章')));
        }

        if (!trim($_POST['title'])) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入文章标题')));
        }

        if (get_setting('upload_enable') == 'Y' AND !$_POST['logo_img'] AND !$_POST['is_suggest'] && !is_mobile()) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请上传文章封面')));
        }

        if (get_setting('category_enable') == 'N') {
            $_POST['category_id'] = 1;
        }

        if (!$_POST['category_id'] && $_POST['is_suggest'] == 1) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请后台添加建议分类')));

        } else if (!$_POST['category_id']) {

            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择文章分类')));
        }

        if (get_setting('question_title_limit') > 0 AND cjk_strlen(trim($_POST['title'])) > get_setting('question_title_limit')) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章标题字数不得大于 %s 字节', get_setting('question_title_limit'))));
        }

        if (!$this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($_POST['message'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        if (!$this->model('publish')->insert_attach_is_self_upload($_POST['message'], $_POST['attach_ids'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('只允许插入当前页面上传的附件')));
        }

        // if (!is_mobile() && human_valid('question_valid_hour') AND !$this->model('tools')->geetest($_POST)) {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('验证码错误')));
        // }
        if ($_POST['topics']) {
            foreach ($_POST['topics'] AS $key => $topic_title) {
                $topic_title = trim($topic_title);

                if (!$topic_title) {
                    unset($_POST['topics'][$key]);
                } else {
                    $_POST['topics'][$key] = $topic_title;
                }
            }

            if (get_setting('question_topics_limit') AND sizeof($_POST['topics']) > get_setting('question_topics_limit')) {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('单个文章话题数量最多为 %s 个, 请调整话题数量', get_setting('question_topics_limit'))));
            }
        }
        if (get_setting('new_question_force_add_topic') == 'Y' AND !$_POST['topics']) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请为文章添加话题')));
        }

        $this->model('draft')->delete_draft(0, 'article', $this->user_id);
        if ($this->publish_approval_valid(array(
            trim($_POST['title']),
            $_POST['message']
        ))) {
            $this->model('publish')->publish_approval('article', array(
                'title' => trim($_POST['title']),
                'message' => $_POST['message'],
                'category_id' => $_POST['category_id'],
                'column_id' => $_POST['column_id'],
                'topics' => $_POST['topics'],
                'article_img' => $_POST['logo_img'],
                'permission_create_topic' => $this->user_info['permission']['create_topic']
            ), $this->user_id);

            H::ajax_json_output(AWS_APP::RSM(null, 2, '等待后台审核'));
        } else {
            $article_id = $this->model('publish')->publish_article(trim($_POST['title']),$_POST['logo_img'], $_POST['message'], $this->user_id, $_POST['topics'], $_POST['category_id'], $_POST['column_id'], null, $this->user_info['permission']['create_topic']);


            H::ajax_json_output(AWS_APP::RSM(array(
                'article_id' => $article_id
            ), 1, null));
        }
    }

    public function modify_article_action()
    {
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        if (!$article_info = $this->model('article')->get_article_info_by_id($_POST['article_id'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章不存在')));
        }

        if ($article_info['lock'] AND !($this->user_info['permission']['is_administortar'] OR $this->user_info['permission']['is_moderator'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章已锁定, 不能编辑')));
        }

        if (!$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator'] AND !$this->user_info['permission']['edit_article']) {
            if ($article_info['uid'] != $this->user_id) {
                H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你没有权限编辑这个文章')));
            }
        }

        if (!trim($_POST['title'])) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请输入文章标题')));
        }
        if (get_setting('upload_enable') == 'Y' AND !$_POST['logo_img'] and !$_POST['is_suggest'] && !is_mobile()) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请上传文章封面')));
        }

        if (get_setting('category_enable') == 'N') {
            $_POST['category_id'] = 1;
        }

        if (!$_POST['category_id'] && $_POST['is_suggest'] == 1) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请后台添加建议分类')));

        } else if (!$_POST['category_id']) {
            H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('请选择文章分类')));
        }

        if (get_setting('question_title_limit') > 0 AND cjk_strlen(trim($_POST['title'])) > get_setting('question_title_limit')) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('文章标题字数不得大于') . ' ' . get_setting('question_title_limit') . ' ' . AWS_APP::lang()->_t('字节')));
        }

        if (!$this->user_info['permission']['publish_url'] AND FORMAT::outside_url_exists($_POST['message'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('你所在的用户组不允许发布站外链接')));
        }

        /*if (human_valid('question_valid_hour') AND !AWS_APP::captcha()->is_validate($_POST['seccode_verify'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('请填写正确的验证码')));
        }*/
        // if (!is_mobile() && human_valid('question_valid_hour') AND !$this->model('tools')->geetest($_POST)) {
        //     H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('验证码错误')));
        // }
        if (!$this->model('publish')->insert_attach_is_self_upload($_POST['message'], $_POST['attach_ids'])) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('只允许插入当前页面上传的附件')));
        }

        
        $item_id = $_POST['article_id']?$_POST['article_id']:0;
        $this->model('draft')->delete_draft($item_id, 'article', $this->user_id);

        if ($_POST['do_delete'] AND !$this->user_info['permission']['is_administortar'] AND !$this->user_info['permission']['is_moderator']) {
            H::ajax_json_output(AWS_APP::RSM(null, '-1', AWS_APP::lang()->_t('对不起, 你没有删除文章的权限')));
        }

        if ($_POST['do_delete']) {
            if ($this->user_id != $article_info['uid']) {
                $this->model('account')->send_delete_message($article_info['uid'], $article_info['title'], $article_info['message']);
            }

            $this->model('article')->remove_article($article_info['id']);

            H::ajax_json_output(AWS_APP::RSM(null,2, '已删除'));
        }

        $this->model('article')->update_article($article_info['id'], $this->user_id, trim($_POST['title']),$_POST['logo_img'], $_POST['message'], $_POST['topics'], $_POST['category_id'], $_POST['column_id'], $this->user_info['permission']['create_topic']);


        H::ajax_json_output(AWS_APP::RSM(array(
            'article_id' => $article_id
        ), 1, null));
    }
    
    

    //话题详情列表
    public function list_action()
    {
        $topic_ids = explode(',', $_GET['topic_id']);

        if ($_GET['per_page'])
        {
            $per_page = intval($_GET['per_page']);
        }
        else
        {
            $per_page = get_setting('contents_per_page');
        }

        if ($_GET['sort_type'] == 'hot')
        {
            $posts_list = $this->model('posts')->get_hot_posts($_GET['post_type'], $_GET['category'], $topic_ids, $_GET['day'], $_GET['page'], $per_page);
        }
        else
        {
            $posts_list = $this->model('posts')->get_posts_list($_GET['post_type'], $_GET['page'], $per_page, $_GET['sort_type'], $topic_ids, $_GET['category'], $_GET['answer_count'], $_GET['day'], $_GET['is_recommend']);
        }

        H::ajax_json_output(AWS_APP::RSM(array('question_info'=>$posts_list), 1 , null));

    }


    public function privacy_setting_action()
    {   
        
        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $settings = objarray_to_array(json_decode($_POST['setting_str']));

        ksort($settings['notification_settings']);
 
        if ($notify_actions = $this->model('notify')->notify_action_details)
        {   
            ksort($notify_actions);

            $notification_setting = array();

            foreach ($notify_actions as $key => $val)
            {   
               
                if (! isset($settings['notification_settings'][$key]) AND $val['user_setting'])
                {
                    $notification_setting[] = intval($key);
                }
            }
        }

        $email_settings = array(
            'FOLLOW_ME' => 'N',
            'QUESTION_INVITE' => 'N',
            'NEW_ANSWER' => 'N',
            'NEW_MESSAGE' => 'N',
            'QUESTION_MOD' => 'N',
        );

        if ($settings['email_settings'])
        {   

            foreach ($settings['email_settings'] AS $key => $val)
            {   

                unset($email_settings[$val]);
            }
        }

        

        $weixin_settings = array(
            'AT_ME' => 'N',
            'NEW_ANSWER' => 'N',
            // 'NEW_ARTICLE_COMMENT',
            'NEW_ARTICLE_ANSWER' => 'N',
            'NEW_COMMENT' => 'N',
        );

        if ($settings['weixin_settings'])
        {
            foreach ($settings['weixin_settings'] AS $key => $val)
            {
                unset($weixin_settings[$val]);
            }
        }

        $this->model('account')->update_users_fields(array(
            'email_settings' => serialize($email_settings),
            'weixin_settings' => serialize($weixin_settings),
            'weibo_visit' => intval($settings['weibo_visit']),
            'inbox_recv' => intval($settings['inbox_recv'])
        ), $this->user_id);
        


        $this->model('account')->update_notification_setting_fields($notification_setting, $this->user_id);

        H::ajax_json_output(AWS_APP::RSM(null, -1, AWS_APP::lang()->_t('隐私设置保存成功')));
    }


    public function focus_topic_action()
    {   

        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        H::ajax_json_output(AWS_APP::RSM(array(
            'type' => $this->model('topic')->add_focus_topic($this->user_id, intval($_POST['topic_id']))
        ), '1', null));
    }

     
    public function check_user_mobile_action()
    {   

        if(!$this->user_id)
        {
            H::ajax_json_output(AWS_APP::RSM(null, -3, AWS_APP::lang()->_t("请登录")));
        }

        $user = $this->model('account')->get_user_info_by_uid($this->user_id);

        if($user['mobile'])
        {
            H::ajax_json_output(AWS_APP::RSM($user['mobile'], -1, AWS_APP::lang()->_t("请先解除绑定")));

        }else
        {
            H::ajax_json_output(AWS_APP::RSM(null, 1, AWS_APP::lang()->_t('可以绑定')));
        }

        

    }

}