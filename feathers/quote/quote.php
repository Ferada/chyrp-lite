<?php
    class Quote extends Feathers implements Feather {
        public function __init() {
            $this->setField(array("attr" => "quote",
                                  "type" => "text_block",
                                  "rows" => 5,
                                  "label" => __("Quote", "quote"),
                                  "preview" => true));
            $this->setField(array("attr" => "source",
                                  "type" => "text_block",
                                  "rows" => 5,
                                  "label" => __("Source", "quote"),
                                  "optional" => true,
                                  "preview" => true));

            $this->setFilter("quote", array("markup_post_text", "markup_text"));
            $this->setFilter("source", array("markup_post_text", "markup_text"));
        }

        public function submit() {
            if (empty($_POST['quote']))
                error(__("Error"), __("Quote can't be empty.", "quote"), null, 422);

            fallback($_POST['source'], "");
            fallback($_POST['slug'], $_POST['quote']);

            return Post::add(array("quote" => $_POST['quote'],
                                   "source" => $_POST['source']));
        }

        public function update($post) {
            if (empty($_POST['quote']))
                error(__("Error"), __("Quote can't be empty."), null, 422);

            fallback($_POST['source'], "");

            return $post->update(array("quote" => $_POST['quote'],
                                       "source" => $_POST['source']));
        }

        public function title($post) {
            return $post->title_from_excerpt();
        }

        public function excerpt($post) {
            return $post->quote;
        }

        public function feed_content($post) {
            $content = '<blockquote>'.$post->quote.'</blockquote>';

            if (!empty($post->source))
                $content.= '<cite>'.$post->source.'</cite>';

            return $content;
        }
    }
