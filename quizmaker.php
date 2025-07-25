<?php
/*
Plugin Name: QuizMaker
Description: サブコンテンツ型・診断投稿＆回答プラグイン（WordPress標準ユーザー対応）
Version: 0.1
Author: silicagel
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
    
    // 統計データを取得
    global $wpdb;
    $quiz_stats = [];
    $user_logs = $wpdb->get_results("
        SELECT meta_value 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'quiz_correct_log'
    ");
    
    foreach ($user_logs as $log_row) {
        $log = maybe_unserialize($log_row->meta_value);
        if (is_array($log)) {
            foreach ($log as $quiz_id => $result) {
                if (!isset($quiz_stats[$quiz_id])) {
                    $quiz_stats[$quiz_id] = ['attempts' => 0, 'correct' => 0];
                }
                $quiz_stats[$quiz_id]['attempts']++;
                if ($result) $quiz_stats[$quiz_id]['correct']++;
            }
        }
    }
    
    ob_start();
    echo '<div style="max-width:600px; margin:0 auto; padding:20px;">';
    echo '<h2>📝 診断一覧</h2>';
    if ($q->have_posts()) {
        echo '<div style="display:grid; gap:15px;">';
        while ($q->have_posts()) {
            $q->the_post();
            $quiz_id = get_the_ID();
            $url = add_query_arg('quiz_id', $quiz_id, '/quiz-detail/');
            $stats = isset($quiz_stats[$quiz_id]) ? $quiz_stats[$quiz_id] : ['attempts' => 0, 'correct' => 0];
            $accuracy = $stats['attempts'] > 0 ? round(($stats['correct'] / $stats['attempts']) * 100, 1) : 0;
            
            echo '<div style="border:1px solid #ddd; padding:15px; border-radius:8px; background:white; box-shadow:0 2px 4px rgba(0,0,0,0.1);">';
            echo '<h3 style="margin:0 0 10px 0;"><a href="'.$url.'" style="text-decoration:none; color:#333;">'.esc_html(get_the_title()).'</a></h3>';
            echo '<p style="margin:5px 0; color:#666; font-size:14px;">作成者: '.get_the_author().'</p>';
            if ($stats['attempts'] > 0) {
                echo '<p style="margin:5px 0; color:#666; font-size:14px;">👥 '.$stats['attempts'].'人が挑戦 | 🎯 正答率 '.$accuracy.'%</p>';
            } else {
                echo '<p style="margin:5px 0; color:#999; font-size:14px;">まだ挑戦者がいません</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p style="text-align:center; color:#666;">まだ診断がありません。</p>';
    }
    echo '</div>';
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
    
    // この診断の統計を取得
    global $wpdb;
    $quiz_stats = ['attempts' => 0, 'correct' => 0];
    $user_logs = $wpdb->get_results("
        SELECT meta_value 
        FROM {$wpdb->usermeta} 
        WHERE meta_key = 'quiz_correct_log'
    ");
    
    foreach ($user_logs as $log_row) {
        $log = maybe_unserialize($log_row->meta_value);
        if (is_array($log) && isset($log[$quiz_id])) {
            $quiz_stats['attempts']++;
            if ($log[$quiz_id]) $quiz_stats['correct']++;
        }
    }
    
    $accuracy = $quiz_stats['attempts'] > 0 ? round(($quiz_stats['correct'] / $quiz_stats['attempts']) * 100, 1) : 0;
    ?>
    <div style="max-width:600px; margin:0 auto; padding:20px;">
        <h2><?php echo esc_html($post->post_title); ?></h2>
        
        <?php if ($quiz_stats['attempts'] > 0): ?>
            <div style="background:#f0f8ff; padding:10px; margin:15px 0; border-radius:5px; font-size:14px;">
                📊 <strong><?php echo $quiz_stats['attempts']; ?>人</strong>が挑戦 | 
                正答率 <strong><?php echo $accuracy; ?>%</strong>
            </div>
        <?php endif; ?>
        
        <form method="post" style="background:#f9f9f9; padding:20px; border-radius:8px; margin:20px 0;">
            <?php
            foreach ($choices as $i => $choice) {
                echo '<label style="display:block; margin:10px 0; padding:10px; background:white; border-radius:5px; cursor:pointer;">';
                echo '<input type="radio" name="selected" value="'.($i+1).'" required style="margin-right:10px;"> ';
                echo esc_html($choice);
                echo '</label>';
            }
            ?>
            <button type="submit" style="background:#4CAF50; color:white; padding:12px 24px; border:none; border-radius:5px; font-size:16px; cursor:pointer; width:100%; margin-top:15px;">回答する</button>
        </form>
        
        <?php echo $feedback; ?>
    </div>
    <?php
    return ob_get_clean();
});

// ▼ ショートコード: 統計表示（全体統計と個人成績）
add_shortcode('quizmaker_stats', function () {
    ob_start();
    ?>
    <div style="max-width:600px; margin:0 auto; padding:20px;">
        <h2>📊 診断統計</h2>
        
        <?php if (is_user_logged_in()): ?>
            <div style="background:#f0f8ff; padding:15px; margin-bottom:20px; border-radius:8px;">
                <h3>🎯 あなたの成績</h3>
                <?php
                $user_id = get_current_user_id();
                $user_log = get_user_meta($user_id, 'quiz_correct_log', true) ?: [];
                if (!empty($user_log)) {
                    $total_attempts = count($user_log);
                    $correct_count = array_sum($user_log);
                    $accuracy = round(($correct_count / $total_attempts) * 100, 1);
                    echo "<p>挑戦した診断数: <strong>{$total_attempts}問</strong></p>";
                    echo "<p>正解数: <strong>{$correct_count}問</strong></p>";
                    echo "<p>正答率: <strong>{$accuracy}%</strong></p>";
                } else {
                    echo "<p>まだ診断に挑戦していません。</p>";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div style="background:#f9f9f9; padding:15px; border-radius:8px;">
            <h3>🏆 人気診断ランキング</h3>
            <?php
            // 全ユーザーの回答ログを集計
            global $wpdb;
            $quiz_stats = [];
            
            // すべてのユーザーのquiz_correct_logを取得
            $user_logs = $wpdb->get_results("
                SELECT meta_value 
                FROM {$wpdb->usermeta} 
                WHERE meta_key = 'quiz_correct_log'
            ");
            
            foreach ($user_logs as $log_row) {
                $log = maybe_unserialize($log_row->meta_value);
                if (is_array($log)) {
                    foreach ($log as $quiz_id => $result) {
                        if (!isset($quiz_stats[$quiz_id])) {
                            $quiz_stats[$quiz_id] = ['attempts' => 0, 'correct' => 0];
                        }
                        $quiz_stats[$quiz_id]['attempts']++;
                        if ($result) $quiz_stats[$quiz_id]['correct']++;
                    }
                }
            }
            
            // 挑戦数でソート
            uasort($quiz_stats, function($a, $b) {
                return $b['attempts'] - $a['attempts'];
            });
            
            $rank = 1;
            foreach (array_slice($quiz_stats, 0, 5, true) as $quiz_id => $stats) {
                $quiz = get_post($quiz_id);
                if ($quiz && $quiz->post_status === 'publish') {
                    $accuracy = $stats['attempts'] > 0 ? round(($stats['correct'] / $stats['attempts']) * 100, 1) : 0;
                    $url = add_query_arg('quiz_id', $quiz_id, '/quiz-detail/');
                    echo "<div style='border-left:4px solid #4CAF50; padding:10px; margin:10px 0; background:white;'>";
                    echo "<strong>{$rank}位</strong> ";
                    echo "<a href='{$url}' style='text-decoration:none; color:#333;'>" . esc_html($quiz->post_title) . "</a><br>";
                    echo "<small>挑戦者: {$stats['attempts']}人 | 正答率: {$accuracy}%</small>";
                    echo "</div>";
                    $rank++;
                }
            }
            
            if (empty($quiz_stats)) {
                echo "<p>まだ統計データがありません。</p>";
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
});
