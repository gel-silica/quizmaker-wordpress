<?php
/*
Plugin Name: QuizMaker
Description: サブコンテンツ型・診断投稿＆回答プラグイン（WordPress標準ユーザー対応）
Version: 0.1
Author: げるのGPT
*/
if (!defined('ABSPATH')) exit;

// ▼ カスタム投稿タイプ登録（診断クイズ用）
add_action('init', function () {
    register_post_type('quiz', [
        'label' => '診断',
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'author'],
    ]);
});

// ▼ ショートコード: 診断投稿フォーム（要ログイン）
add_shortcode('quizmaker_post_form', function () {
    if (!is_user_logged_in()) {
        return '<div>診断を作成するには<a href="/login/">ログイン</a>が必要です。</div>';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_question'])) {
        $quiz_id = wp_insert_post([
            'post_type'    => 'quiz',
            'post_title'   => sanitize_text_field($_POST['quiz_question']),
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
        ]);
        if ($quiz_id) {
            // 選択肢＆正解をカスタムフィールドに保存
            $choices = array_map('sanitize_text_field', $_POST['choices']);
            update_post_meta($quiz_id, 'quiz_choices', json_encode($choices));
            update_post_meta($quiz_id, 'quiz_answer', intval($_POST['quiz_answer']));
            echo '<div style="color:green;">診断を作成しました！</div>';
        }
    }

    ob_start();
    ?>
    <form method="post">
        <label>問題文：<input name="quiz_question" required></label><br>
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <label>選択肢<?php echo $i; ?>：<input name="choices[]" required></label><br>
        <?php endfor; ?>
        <label>正解番号（1〜4）：<input name="quiz_answer" type="number" min="1" max="4" required></label><br>
        <button type="submit">診断を作成</button>
    </form>
    <?php
    return ob_get_clean();
});

// ▼ ショートコード: 診断一覧表示（全体公開）
add_shortcode('quizmaker_list', function () {
    $q = new WP_Query([
        'post_type'      => 'quiz',
        'posts_per_page' => 10,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC'
    ]);
    ob_start();
    echo '<h2>診断一覧</h2>';
    if ($q->have_posts()) {
        echo '<ul>';
        while ($q->have_posts()) {
            $q->the_post();
            $url = add_query_arg('quiz_id', get_the_ID(), '/quiz-detail/');
            echo '<li><a href="'.$url.'">'.esc_html(get_the_title()).'</a>（作成者: '.get_the_author().')</li>';
        }
        echo '</ul>';
    } else {
        echo 'まだ診断がありません。';
    }
    wp_reset_postdata();
    return ob_get_clean();
});

// ▼ ショートコード: 診断詳細＆回答（/quiz-detail/ で使用想定）
add_shortcode('quizmaker_detail', function () {
    $quiz_id = intval($_GET['quiz_id'] ?? 0);
    if (!$quiz_id || get_post_type($quiz_id) !== 'quiz') return '<div>診断が見つかりません。</div>';

    $post = get_post($quiz_id);
    $choices = json_decode(get_post_meta($quiz_id, 'quiz_choices', true), true);
    $answer  = get_post_meta($quiz_id, 'quiz_answer', true);
    
    // データの妥当性チェック
    if (!$choices || !is_array($choices) || empty($choices)) {
        return '<div>診断データに問題があります。</div>';
    }

    // 回答処理
    $feedback = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected'])) {
        $selected = intval($_POST['selected']);
        $is_correct = ($selected === intval($answer));
        if ($is_correct) {
            $feedback = '<div style="color:green;">正解！お見事🌸</div>';
        } else {
            $correct_text = isset($choices[$answer-1]) ? $choices[$answer-1] : '不明';
            $feedback = '<div style="color:red;">残念！正解は「'.esc_html($correct_text).'」</div>';
        }

        // 正答記録（ログイン時のみ保存例：user_metaに記録、DB拡張も可）
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $log = get_user_meta($user_id, 'quiz_correct_log', true) ?: [];
            if (!is_array($log)) $log = [];
            $log[$quiz_id] = $is_correct ? 1 : 0;
            update_user_meta($user_id, 'quiz_correct_log', $log);
            $feedback .= '<div>※会員登録済みのため、正答記録を保存しました。</div>';
        } else {
            $feedback .= '<div>※会員登録すると正答記録が保存できます！</div>';
        }
    }

    ob_start();
    ?>
    <h2><?php echo esc_html($post->post_title); ?></h2>
    <form method="post">
        <?php
        foreach ($choices as $i => $choice) {
            echo '<label><input type="radio" name="selected" value="'.($i+1).'" required> '.esc_html($choice).'</label><br>';
        }
        ?>
        <button type="submit">回答する</button>
    </form>
    <?php echo $feedback; ?>
    <?php
    return ob_get_clean();
});
