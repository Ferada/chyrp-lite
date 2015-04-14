<?php
    require_once "model.Like.php";

    class Likes extends Modules {
        static function __install() {
            Like::install();
        }

        static function __uninstall($confirm) {
            if ($confirm)
                Like::uninstall();
        }

        static function admin_like_settings($admin) {
            $config = Config::current();

            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));

            if (empty($_POST))
                return $admin->display("like_settings");

            if (!isset($_POST['hash']) or $_POST['hash'] != $config->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $set = array($config->set("module_like",
                                array("showOnFront" => isset($_POST['showOnFront']),
                                      "likeWithText" => isset($_POST['likeWithText']),
                                      "likeImage" => $_POST['likeImage'])));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=like_settings");
        }

        static function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["like_settings"] = array("title" => __("Likes", "likes"));

            return $navs;
        }

        static function route_like() {
            $request["action"] = "like";
            $request["post_id"] = (int) fallback($_GET['post_id'], 0);

            $like = new Like($request, Visitor::current()->id);
            $like->like();

            $post = new Post($request["post_id"]);

            Flash::notice(__("Post liked.", "likes"), $post->url()."#likes_post-".$request["post_id"]);
        }

        static function route_unlike() {
            $request["action"] = "unlike";
            $request["post_id"] = (int) fallback($_GET['post_id'], 0);

            $like = new Like($request, Visitor::current()->id);
            $like->unlike();

            $post = new Post($request["post_id"]);

            Flash::notice(__("Post unliked.", "likes"), $post->url()."#likes_post-".$request["post_id"]);
        }

        static function stylesheets($styles) {
            $styles[] = Config::current()->chyrp_url."/modules/likes/style.css";
            return $styles;
        }

        static function scripts($scripts) {
            $scripts[] = Config::current()->chyrp_url."/modules/likes/javascript.php";
            return $scripts;
        }

        static function ajax_like() {
            header("Content-type: text/json");

            if (!isset($_REQUEST["action"]) or !isset($_REQUEST["post_id"]))
                exit();
            
            $user_id = Visitor::current()->id;
            $likeSetting = Config::current()->module_like;
            $responseObj = array();
            $responseObj["uid"] = $user_id;
            $responseObj["success"] = true;
            
            try {
                $like = new Like($_REQUEST, $user_id);
                $likeText = "";

                if ($like->action != "like")
                    throw new Exception("invalid action");

                $like->like();
                $like->fetchCount();

                if ($like->total_count == 0)
                    $likeText = __("No likes yet.", "likes");
                elseif ($like->total_count == 1)
                    $likeText = _f("You like this.", $like->total_count, "likes");
                elseif ($like->total_count == 2)
                    $likeText = _f("You and %d person like this.", ($like->total_count - 1), "likes");
                else
                    $likeText = _f("You and %d people like this.", ($like->total_count - 1), "likes");

                $responseObj["likeText"] = $likeText;
            }
            catch(Exception $e) {
                $responseObj["success"] = false;
                $responseObj["error_txt"] = $e->getMessage();
            }
            echo json_encode($responseObj);
        }

        static function ajax_unlike() {
            header("Content-type: text/json");

            if (!isset($_REQUEST["action"]) or !isset($_REQUEST["post_id"]))
                exit();
            
            $user_id = Visitor::current()->id;
            $likeSetting = Config::current()->module_like;
            $responseObj = array();
            $responseObj["uid"] = $user_id;
            $responseObj["success"] = true;
            
            try {
                $like = new Like($_REQUEST, $user_id);
                $likeText = "";

                if ($like->action != "unlike")
                    throw new Exception("invalid action");

                $like->unlike();
                $like->fetchCount();

                if ($like->total_count == 0)
                    $likeText = __("No likes yet.", "likes");
                elseif ($like->total_count == 1)
                    $likeText = _f("%d person likes this.", $like->total_count, "likes");
                else
                    $likeText = _f("%d people like this.", $like->total_count, "likes");

                $responseObj["likeText"] = $likeText;
            }
            catch(Exception $e) {
                $responseObj["success"] = false;
                $responseObj["error_txt"] = $e->getMessage();
            }
            echo json_encode($responseObj);
        }

        static function delete_post($post) {
            SQL::current()->delete("likes", array("post_id" => $post->id));
        }

        static function delete_user($user) {
            SQL::current()->update("likes", array("user_id" => $user->id), array("user_id" => 0));
        }

        public function post($post) {
            $post->has_many[] = "likes";
            $post->get_likes = self::get_likes($post);
        }

        static function get_likes($post) {
            $config = Config::current();
            $route = Route::current();
            $visitor = Visitor::current();
            $likeSetting = $config->module_like;

            if ($likeSetting["showOnFront"] == false and $route->action == "index")
                return;

            $request["action"] = $route->action;
            $request["post_id"] = $post->id;
            $like = new Like($request, $visitor->id);
            $like->cookieInit();
            $hasPersonLiked = false;

            if ($like->session_hash != null) {
                $people = $like->fetchPeople();
                if (count($people) != 0)
                    foreach ($people as $person)
                        if ($person["session_hash"] == $like->session_hash) {
                            $hasPersonLiked = true;
                            break;
                        }
            } else $like->fetchCount();

            $returnStr = "<div class='likes' id='likes_post-$post->id'>";

            if (!$hasPersonLiked) {
                if ($visitor->group->can("like_post")) {
                    $returnStr.= "<a class=\"likes like\" href=\"".$config->chyrp_url."/?action=like&post_id=".$request["post_id"]."\" data-post_id=\"".$request["post_id"]."\">";
                    $returnStr.= "<img src=\"".$likeSetting["likeImage"]."\" alt='Likes icon'>";
                    if ($likeSetting["likeWithText"]) {
                        $returnStr.= " <span class='like'>".__("Like!", "likes")."</span>";
                        $returnStr.= " <span class='unlike'>".__("Unlike!", "likes")."</span>";
                    }
                    $returnStr.= "</a>";
                }

                $returnStr.= " <span class='like_text'>";
                if ($like->total_count == 0)
                    $returnStr.= __("No likes yet.", "likes");
                elseif ($like->total_count == 1)
                    $returnStr.= _f("%d person likes this.", $like->total_count, "likes");
                else
                    $returnStr.= _f("%d people like this.", $like->total_count, "likes");
                $returnStr.= "</span>";


            } else {
                if ($visitor->group->can("unlike_post")) {
                    $returnStr.= "<a class=\"likes liked\" href=\"".$config->chyrp_url."/?action=unlike&post_id=".$request["post_id"]."\" data-post_id=\"".$request["post_id"]."\">";
                    $returnStr.= "<img src=\"".$likeSetting["likeImage"]."\" alt='Likes icon'>";
                    if ($likeSetting["likeWithText"]) {
                        $returnStr.= " <span class='like'>".__("Like!", "likes")."</span>";
                        $returnStr.= " <span class='unlike'>".__("Unlike!", "likes")."</span>";
                    }
                    $returnStr.= "</a>";
                }

                $returnStr.= " <span class='like_text'>";
                if ($like->total_count == 0)
                    $returnStr.= __("No likes yet.", "likes");
                elseif ($like->total_count == 1)
                    $returnStr.= _f("You like this.", $like->total_count, "likes");
                elseif ($like->total_count == 2)
                    $returnStr.= _f("You and %d person like this.", ($like->total_count - 1), "likes");
                else
                    $returnStr.= _f("You and %d people like this.", ($like->total_count - 1), "likes");
                $returnStr.= "</span>";

            }

            $returnStr.= "</div>";
            return $post->get_likes = $returnStr;
        }

        public function get_like_images() {
            $imagesDir = MODULES_DIR."/likes/images/";
            $images = glob($imagesDir . "*.{jpg,jpeg,png,gif,svg}", GLOB_BRACE);

            foreach ($images as $image) {
                $pattern = "/\/(\w.*)\/images\//";
                $image = preg_replace($pattern, "", $images);
                while (list($key, $val) = each($image))
                    $arr[] = Config::current()->chyrp_url."/modules/likes/images/$val";

                return array_combine($image, $arr);
            }
        }
    }
