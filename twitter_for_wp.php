<?php
/*
Plugin Name: twitter for wp
Description: twitter for wp
Author: ふぁ
Version: 1.0
*/

require "twitteroauth/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;

class twitter_for_wp_main
{
    function __construct()
    {
        add_action('admin_menu', array($this, 'add_pages'));
        add_action('publish_post', array($this, 'on_post_publish'), 10, 2);
        add_action('init', array($this, 'register_session'));
        add_action('my_new_event', array($this, 'my_activation'));
    }

    function register_session()
    {
        if (!session_id())
            session_start();
    }

    function add_pages()
    {
        add_menu_page('twitter for wp', 'twitter for wp',  'level_8', __FILE__, array($this, 'show_text_option_page'), '', 26);
    }
    function debug_add($id){
        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;
        $show_text["tweet"]["num"]++;
        $num =  $show_text["tweet"]["num"];
        $show_text["tweet"]["list"][$num]["id"] = $id;
        $show_text["tweet"]["list"][$num]["time"] = time();
        unset($show_text["tweet"]["list"][$num - 10]);
        update_option('showtext_options', $show_text);
    }
    function on_post_publish($id, $post)
    {
        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;

        if ($show_text["last_id"] ==  $post->ID) {
            return;
        }
        $show_text["last_id"] = $post->ID;
        update_option('showtext_options', $show_text);


        $data = get_posts('post_type=post&order=DESC&orderby=date&showposts=1');
        if (isset($data[0])) {
            if ($data[0]->ID != $id) {
                return;
            }
        } else {
            return;
        }
        if ($post->post_title != null) {
            $opt = get_option('showtext_options');
            $show_text = isset($opt) ? $opt : null;
            $userConnect = new TwitterOAuth($show_text["consumerKey"], $show_text["consumerSecrect"], $show_text['oauth_token'], $show_text['oauth_token_secret']);
            $userInfo = $userConnect->get('account/verify_credentials');
            if (isset($userInfo->id_str)) {
                $tweet = $post->post_title . "\n" . $post->guid;

                $params = array("status" => $tweet);
                $result = $userConnect->post(
                    'statuses/update',
                    $params
                );

                $show_text["tweet"]["num"]++;
                $num =  $show_text["tweet"]["num"];
                $show_text["tweet"]["list"][$num]["id"] = $result->id;
                $show_text["tweet"]["list"][$num]["time"] = time();
                unset($show_text["tweet"]["list"][$num - 10]);
                update_option('showtext_options', $show_text);
            }
        }
    }
    function show_text_option_page()
    {

        function get_text()
        {
            $opt = get_option('showtext_options');
            return isset($opt) ? $opt : null;
        }

        wp_nonce_field('shoptions');
        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;

?>
<h2>twitterapi設定</h2>
<form action="" method="post">
    <input name="showtext_options[consumerKey]" type="text" id="inputtext" placeholder="consumerKey"
        value="<?php echo $show_text["consumerKey"] ?>" />
    <input name="showtext_options[consumerSecrect]" type="text" id="inputtext" placeholder="consumerSecrect"
        value="<?php echo $show_text["consumerSecrect"] ?>" />
    <p>Callback URL：<?php echo plugin_dir_url(__FILE__) . "login/login.php" ?></p>
    <input type="submit" name="Submit" class="button-primary" value="変更を保存" />
</form>
<h2>ログイン情報</h2>
<p>oauth_token：<?php echo $show_text["oauth_token"] ?></p>
<p>oauth_token_secret：<?php echo $show_text["oauth_token_secret"] ?></p>
<?php


if (isset($_POST['showtext_options'])) {
    $opt = $_POST['showtext_options'];
    update_option('showtext_options', $opt);
    _e('保存しました');
}

        if ($show_text["tweet"]["num"] != null) {
            echo "<h2>捕捉中のツイート</h2>";
            foreach ($show_text["tweet"]["list"] as $tweet) {
                echo '<p style="margin: 0px;">TweetID：' . $tweet["id"] . "</p>";
                echo '<p style="margin-top: 0px;">RT：' . $tweet["rt"] . "</p>";
            }
        }
        echo "<p>" . json_encode($show_text) . "</p>";


        if ($show_text["consumerKey"] and $show_text["consumerSecrect"]) {
            $callback = plugin_dir_url(__FILE__) . "login/login.php";
            $twitterConnect = new TwitterOAuth(
                $show_text["consumerKey"],
                $show_text["consumerSecrect"]
            );

            $requestToken = $twitterConnect->oauth(
                'oauth/request_token',
                array('oauth_callback' => $callback)
            );

            $_SESSION['oauth_token'] = $requestToken['oauth_token'];
            $_SESSION['oauth_token_secret'] =  $requestToken['oauth_token_secret'];

            $url = $twitterConnect->url(
                'oauth/authorize',
                [
                    'oauth_token' => $requestToken['oauth_token']
                ]
            );

            echo '<a href=' . $url . '><input type="submit" name="Submit" class="button-primary" value="TwitterLogin"></a>';
        }


        if ($_GET['oauth_verifier']) {
            $oauthToken = $_SESSION['oauth_token'];
            $oauthTokenSecret = $_SESSION['oauth_token_secret'];

            $twitterConnect = new TwitterOAuth(
                $show_text["consumerKey"],
                $show_text["consumerSecrect"],
                $oauthToken,
                $oauthTokenSecret
            );
            $accessToken = $twitterConnect->oauth(
                'oauth/access_token',
                array(
                    'oauth_verifier' => $_GET['oauth_verifier'],
                    'oauth_token' => $_GET['oauth_token']
                )
            );

            $userConnect = new TwitterOAuth(
                $show_text["consumerKey"],
                $show_text["consumerSecrect"],
                $accessToken['oauth_token'],
                $accessToken['oauth_token_secret']
            );


            $userInfo = $userConnect->get('account/verify_credentials');

            if (isset($userInfo->id_str)) {
                $show_text["oauth_token"] = $accessToken['oauth_token'];
                $show_text["oauth_token_secret"] = $accessToken['oauth_token_secret'];
                update_option('showtext_options', $show_text);
                _e('ログインに成功しました');
            } else {
                _e('ログインに失敗しました');
            }
        }
    }
    function my_activation()
    {
        $opt = get_option('showtext_options');
        $show_text = isset($opt) ? $opt : null;
        if ($show_text['oauth_token']) {

            $userConnect = new TwitterOAuth($show_text["consumerKey"], $show_text["consumerSecrect"], $show_text['oauth_token'], $show_text['oauth_token_secret']);

            foreach ($show_text["tweet"]["list"] as $i => $tweet) {
                if ($tweet["id"]) {
                    $tweet_data = $userConnect->get('statuses/show', array('id' =>  $tweet["id"]));
                    if ($show_text["tweet"]["list"][$i]["time"] != null) {
                        if ($show_text["tweet"]["list"][$i]["time"] + 86400 * 2 < time()) {
                            $show_text["tweet"]["list"][$i]["time"] = null;
                            $tweet_str = "再掲載：" . $tweet_data->text;
                            $params = array("status" => $tweet_str);
                            $result = $userConnect->post(
                                'statuses/update',
                                $params
                            );
                            $show_text["tweet"]["list"][$i]["re"] = $result->id;
                        }
                    }
                    $rt = $tweet_data->retweet_count;
                    if ($show_text["tweet"]["list"][$i]["re"]) {
                        $re_tweet_data = $userConnect->get('statuses/show', array('id' => $show_text["tweet"]["list"][$i]["re"]));
                        $rt = $tweet_data->retweet_count + $re_tweet_data->retweet_count;
                    }
                    if (floor($rt / 100) * 100 > 0) {
                        if (floor($rt  / 100) * 100 > floor($tweet["rt"] / 100) * 100) {
                            $f = floor($rt  / 100) * 100;
                            $tweet_str = (string)$f . "RT：" . $tweet_data->text;
                            $params = array("status" => $tweet_str);
                            $result = $userConnect->post(
                                'statuses/update',
                                $params
                            );
                        }
                    }
                    $show_text["tweet"]["list"][$i]["rt"] = $rt;
                }
            }
            update_option('showtext_options', $show_text);
        }
    }
}

$showtext = new twitter_for_wp_main;

if (!wp_next_scheduled('my_new_event')) {
    wp_schedule_single_event(time() + 600, 'my_new_event');
}