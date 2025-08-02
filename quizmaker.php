<?php
/*
Plugin Name: QuizMaker
Description: サブコンテンツ型・クイズ投稿＆回答プラグイン（WordPress標準ユーザー対応）
Version: 0.1
Author: silicagel
*/
if (!defined('ABSPATH')) exit;

// ▼ CSSファイルの読み込み
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('quizmaker-style', plugin_dir_url(__FILE__) . 'assets/css/quizmaker.css', array(), '1.0.0');
    
    // jQueryの依存関係を確保
    wp_enqueue_script('jquery');
    
    // JavaScriptをインライン追加（他のプラグインによるリンク変換対策）
    wp_add_inline_script('jquery', '
    jQuery(document).ready(function($) {
        // クイズアイテムのクリック処理
        $(".quizmaker-quiz-item").on("click", function(e) {
            if (e.target.tagName !== "BUTTON" && e.target.tagName !== "INPUT") {
                var link = $(this).find(".quizmaker-quiz-link");
                if (link.length > 0) {
                    var href = link.attr("href");
                    if (href) {
                        window.location.href = href;
                    }
                } else {
                    // リンクが変換されている場合の代替処理
                    var titleElement = $(this).find(".quizmaker-quiz-title");
                    var quizId = titleElement.data("quiz-id");
                    if (quizId) {
                        var currentUrl = window.location.href.split("?")[0];
                        window.location.href = currentUrl + "?quiz_id=" + quizId;
                    }
                }
            }
        });
        
        // カーソルスタイルの調整
        $(".quizmaker-quiz-item").css("cursor", "pointer");
    });
    ');
});

// ▼ 管理画面に設定項目を追加
add_action('admin_menu', function () {
    add_options_page(
        'QuizMaker設定',
        'QuizMaker設定',
        'manage_options',
        'quizmaker-settings',
        function () {
            ?>
            <div class="wrap">
                <h1>QuizMaker設定</h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('quizmaker_settings_group');
                    do_settings_sections('quizmaker-settings');
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }
    );
});

add_action('admin_init', function () {
    register_setting('quizmaker_settings_group', 'quizmaker_login_url');
    
    // 基本設定セクション
    add_settings_section('quizmaker_main_section', '基本設定', null, 'quizmaker-settings');
    add_settings_field(
        'quizmaker_login_url',
        'ログインページURL',
        function () {
            $value = esc_url(get_option('quizmaker_login_url', wp_login_url()));
            echo '<input type="url" name="quizmaker_login_url" value="' . $value . '" class="regular-text" placeholder="例: https://example.com/login/">';
            echo '<p class="description">ログインページのURLを設定してください。空の場合はWordPressデフォルトのログインページが使用されます。</p>';
        },
        'quizmaker-settings',
        'quizmaker_main_section'
    );
    
    // ショートコード一覧セクション
    add_settings_section(
        'quizmaker_shortcodes_section', 
        'ショートコード一覧', 
        function () {
            echo '<p>以下のショートコードを投稿や固定ページで使用できます。</p>';
        }, 
        'quizmaker-settings'
    );
    
    add_settings_field(
        'quizmaker_shortcodes_list',
        '利用可能なショートコード',
        function () {
            $shortcodes = [
                [
                    'code' => '[quizmaker_post_form]',
                    'title' => 'クイズ投稿フォーム',
                    'description' => 'ログイン済みユーザーがクイズを作成できるフォームを表示します。'
                ],
                [
                    'code' => '[quizmaker_list]',
                    'title' => 'クイズ一覧',
                    'description' => '作成されたクイズの一覧を表示します。クリックで詳細ページに移動できます。'
                ],
                [
                    'code' => '[quizmaker_detail]',
                    'title' => 'クイズ詳細・回答',
                    'description' => 'URLパラメータ quiz_id で指定されたクイズの詳細と回答フォームを表示します。'
                ],
                [
                    'code' => '[quizmaker_stats]',
                    'title' => '統計表示',
                    'description' => '全体の統計情報と個人成績、人気クイズランキングを表示します。'
                ]
            ];
            
            echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 5px;">';
            foreach ($shortcodes as $shortcode) {
                echo '<div style="margin-bottom: 20px; padding: 15px; background: white; border-left: 4px solid #0073aa; border-radius: 3px;">';
                echo '<h4 style="margin: 0 0 8px 0; color: #0073aa;">' . esc_html($shortcode['title']) . '</h4>';
                echo '<code style="background: #f1f1f1; padding: 4px 8px; border-radius: 3px; font-family: Consolas, Monaco, monospace;">' . esc_html($shortcode['code']) . '</code>';
                echo '<p style="margin: 8px 0 0 0; color: #666;">' . esc_html($shortcode['description']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            
            echo '<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 5px;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #856404;">📋 使用例</h4>';
            echo '<p style="margin: 0; color: #856404;">クイズ投稿ページ: <code>[quizmaker_post_form]</code></p>';
            echo '<p style="margin: 5px 0 0 0; color: #856404;">クイズ一覧ページ: <code>[quizmaker_list]</code></p>';
            echo '<p style="margin: 5px 0 0 0; color: #856404;">クイズ詳細ページ: <code>[quizmaker_list][quizmaker_detail]</code> (両方記載)</p>';
            echo '<p style="margin: 5px 0 0 0; color: #856404;">統計ページ: <code>[quizmaker_stats]</code></p>';
            echo '</div>';
        },
        'quizmaker-settings',
        'quizmaker_shortcodes_section'
    );
});

// ▼ カスタム投稿タイプ登録（クイズクイズ用）
add_action('init', function () {
    register_post_type('quiz', [
        'label' => 'クイズ',
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'author'],
    ]);
});

// ▼ クイズ詳細ページURLを生成する関数
function quizmaker_get_detail_url($quiz_id, $current_url = null) {
    if (!$current_url) {
        global $wp;
        $current_url = home_url(add_query_arg(array(), $wp->request));
    }
    // 既存のquiz_idパラメータを削除してから新しいものを追加
    $url = remove_query_arg('quiz_id', $current_url);
    return add_query_arg('quiz_id', $quiz_id, $url);
}

// ▼ ショートコード: クイズ投稿フォーム（要ログイン）
add_shortcode('quizmaker_post_form', function () {
    if (!is_user_logged_in()) {
        $login_url = get_option('quizmaker_login_url', wp_login_url(get_permalink()));
        return '<div class="quizmaker-container"><div class="quizmaker-feedback info">クイズを作成するには<a href="' . esc_url($login_url) . '">ログイン</a>が必要です。</div></div>';
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
            update_post_meta($quiz_id, 'quiz_choices', json_encode($choices, JSON_UNESCAPED_UNICODE));
            update_post_meta($quiz_id, 'quiz_answer', intval($_POST['quiz_answer']));
            echo '<div class="quizmaker-feedback success">クイズを作成しました！</div>';
        }
    }

    ob_start();
    ?>
    <div class="quizmaker-container">
        <form method="post" class="quizmaker-form">
            <div class="quizmaker-form-group">
                <label class="quizmaker-label">問題文：</label>
                <input name="quiz_question" class="quizmaker-input" required>
            </div>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="quizmaker-form-group">
                    <label class="quizmaker-label">選択肢<?php echo $i; ?>：</label>
                    <input name="choices[]" class="quizmaker-input" required>
                </div>
            <?php endfor; ?>
            <div class="quizmaker-form-group">
                <label class="quizmaker-label">正解番号（1〜4）：</label>
                <input name="quiz_answer" type="number" min="1" max="4" class="quizmaker-input" required>
            </div>
            <button type="submit" class="quizmaker-btn quizmaker-btn-primary quizmaker-btn-full">クイズを作成</button>
        </form>
    </div>
    <?php
    return ob_get_clean();
});

// ▼ ショートコード: クイズ一覧表示（全体公開）
add_shortcode('quizmaker_list', function () {
    // 削除処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz']) && is_user_logged_in()) {
        $quiz_id = intval($_POST['delete_quiz']);
        $quiz = get_post($quiz_id);
        
        // 作成者本人か確認
        if ($quiz && $quiz->post_author == get_current_user_id()) {
            wp_delete_post($quiz_id, true); // 完全削除
            echo '<div class="quizmaker-feedback success">クイズを削除しました。</div>';
        }
    }

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
    echo '<div class="quizmaker-container">';
    echo '<h2 class="quizmaker-title">📝 クイズ一覧</h2>';
    if ($q->have_posts()) {
        echo '<div class="quizmaker-grid">';
        while ($q->have_posts()) {
            $q->the_post();
            $quiz_id = get_the_ID();
            $url = quizmaker_get_detail_url($quiz_id);
            $stats = isset($quiz_stats[$quiz_id]) ? $quiz_stats[$quiz_id] : ['attempts' => 0, 'correct' => 0];
            $accuracy = $stats['attempts'] > 0 ? round(($stats['correct'] / $stats['attempts']) * 100, 1) : 0;
            
            echo '<div class="quizmaker-quiz-item" data-quiz-id="'.$quiz_id.'">';
            
            // ログインユーザーの回答履歴をチェック
            $answered_class = '';
            $answered_indicator = '';
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user_log = get_user_meta($user_id, 'quiz_correct_log', true) ?: [];
                if (isset($user_log[$quiz_id])) {
                    $answered_class = ' quizmaker-answered';
                    $answered_indicator = $user_log[$quiz_id] ? ' ✅' : ' ❌';
                }
            }
            
            echo '<h3 class="quizmaker-quiz-title' . $answered_class . '" data-quiz-id="'.$quiz_id.'"><a href="'.esc_url($url).'" class="quizmaker-quiz-link">'.esc_html(get_the_title()) . $answered_indicator . '</a></h3>';
            echo '<p class="quizmaker-quiz-meta">👤 作成者: '.get_the_author().'</p>';
            if ($stats['attempts'] > 0) {
                echo '<div class="quizmaker-quiz-stats">';
                echo '<span>👥 '.$stats['attempts'].'人が挑戦</span>';
                echo '<span>🎯 正答率 '.$accuracy.'%</span>';
                echo '</div>';
            } else {
                echo '<p class="quizmaker-quiz-meta quizmaker-text-muted">まだ挑戦者がいません</p>';
            }
            
            // 削除ボタン（作成者のみ）
            if (is_user_logged_in() && get_the_author_meta('ID') == get_current_user_id()) {
                echo '<form method="post" style="margin-top: 10px;" onsubmit="return confirm(\'本当に削除しますか？\')">';
                echo '<input type="hidden" name="delete_quiz" value="'.$quiz_id.'">';
                echo '<button type="submit" class="quizmaker-btn" style="background: #e53e3e; color: white; padding: 6px 12px; font-size: 12px;">削除</button>';
                echo '</form>';
            }
            
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="quizmaker-empty">まだクイズがありません。</div>';
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
});

// ▼ ショートコード: クイズ詳細＆回答（/quiz-detail/ で使用想定）
add_shortcode('quizmaker_detail', function () {
    $quiz_id = intval($_GET['quiz_id'] ?? 0);
    
    // quiz_idがない場合は何も表示しない（一覧と併用時）
    if (!$quiz_id) {
        return '';
    }
    
    if (get_post_type($quiz_id) !== 'quiz') {
        return '<div class="quizmaker-container"><div class="quizmaker-feedback error">クイズが見つかりません。</div></div>';
    }

    // 削除処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_quiz']) && is_user_logged_in()) {
        $quiz = get_post($quiz_id);
        if ($quiz && $quiz->post_author == get_current_user_id()) {
            wp_delete_post($quiz_id, true);
            return '<div class="quizmaker-container"><div class="quizmaker-feedback success">クイズを削除しました。<a href="javascript:history.back()">戻る</a></div></div>';
        }
    }

    $post = get_post($quiz_id);
    $choices_json = get_post_meta($quiz_id, 'quiz_choices', true);
    $choices = json_decode($choices_json, true, 512, JSON_UNESCAPED_UNICODE);
    $answer  = get_post_meta($quiz_id, 'quiz_answer', true);
    
    // データの妥当性チェック
    if (!$choices || !is_array($choices) || empty($choices)) {
        return '<div>クイズデータに問題があります。</div>';
    }

    // 回答処理
    $feedback = '';
    $is_first_attempt = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected'])) {
        $selected = intval($_POST['selected']);
        $is_correct = ($selected === intval($answer));
        
        // 正答記録（ログイン時のみ保存例：user_metaに記録、DB拡張も可）
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $log = get_user_meta($user_id, 'quiz_correct_log', true) ?: [];
            if (!is_array($log)) $log = [];
            
            // 初回回答かどうかをチェック
            $is_first_attempt = !isset($log[$quiz_id]);
            
            // 初回の場合のみ統計に記録
            if ($is_first_attempt) {
                $log[$quiz_id] = $is_correct ? 1 : 0;
                update_user_meta($user_id, 'quiz_correct_log', $log);
            }
        }
        
        if ($is_correct) {
            $feedback = '<div style="color:green;">正解！お見事🌸</div>';
        } else {
            $correct_text = isset($choices[$answer-1]) ? html_entity_decode($choices[$answer-1], ENT_QUOTES, 'UTF-8') : '不明';
            $feedback = '<div style="color:red;">残念！正解は「'.esc_html($correct_text).'」</div>';
        }

        if (is_user_logged_in()) {
            if ($is_first_attempt) {
                $feedback .= '<div style="color:#666; font-size:0.9em; margin-top:8px;">※初回回答のため、正答率の統計に反映されました。</div>';
            } else {
                $feedback .= '<div style="color:#666; font-size:0.9em; margin-top:8px;">※この問題は既に回答済みのため、統計には反映されません。</div>';
            }
        } else {
            $feedback .= '<div style="color:#666; font-size:0.9em; margin-top:8px;">※会員登録すると正答記録が保存できます！</div>';
        }
    }

    ob_start();
    
    // このクイズの統計を取得
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
    <div class="quizmaker-container">
        <div class="quizmaker-quiz-header">
            <h2 class="quizmaker-quiz-question"><?php echo esc_html($post->post_title); ?></h2>
            <p class="quizmaker-quiz-meta">👤 作成者: <?php echo get_the_author_meta('display_name', $post->post_author); ?></p>
        
            <?php if ($quiz_stats['attempts'] > 0): ?>
                <div class="quizmaker-stats-box">
                    📊 <strong><?php echo $quiz_stats['attempts']; ?>人</strong>が挑戦 | 
                    正答率 <strong><?php echo $accuracy; ?>%</strong>
                </div>
            <?php endif; ?>
            
            <?php 
            // ユーザーの回答履歴をチェック
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user_log = get_user_meta($user_id, 'quiz_correct_log', true) ?: [];
                if (isset($user_log[$quiz_id])) {
                    $user_result = $user_log[$quiz_id] ? '正解' : '不正解';
                    echo '<div style="background:#f0f8ff; padding:12px; border-radius:6px; margin:16px 0; border-left:4px solid #4299e1;">';
                    echo '🔄 あなたはこの問題に回答済みです（初回結果: ' . $user_result . '）<br>';
                    echo '<small style="color:#666;">再挑戦は可能ですが、統計には初回結果のみが反映されます。</small>';
                    echo '</div>';
                }
            }
            ?>
            
            <?php if (is_user_logged_in() && $post->post_author == get_current_user_id()): ?>
                <form method="post" style="margin: 16px 0;" onsubmit="return confirm('本当に削除しますか？')">
                    <input type="hidden" name="delete_quiz" value="<?php echo $quiz_id; ?>">
                    <button type="submit" class="quizmaker-btn" style="background: #e53e3e; color: white;">このクイズを削除</button>
                </form>
            <?php endif; ?>
        </div>
        
        <form method="post" class="quizmaker-choices">
            <?php
            foreach ($choices as $i => $choice) {
                // 文字エンコーディングを確実に処理
                $choice_text = html_entity_decode($choice, ENT_QUOTES, 'UTF-8');
                echo '<label class="quizmaker-choice">';
                echo '<input type="radio" name="selected" value="'.($i+1).'" required> ';
                echo esc_html($choice_text);
                echo '</label>';
            }
            ?>
            <button type="submit" class="quizmaker-btn quizmaker-btn-primary quizmaker-btn-full">回答する</button>
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
    <div class="quizmaker-container">
        <h2 class="quizmaker-title">📊 クイズ統計</h2>
        
        <?php if (is_user_logged_in()): ?>
            <div class="quizmaker-stats-section quizmaker-personal-stats">
                <h3 class="quizmaker-stats-title">🎯 あなたの成績</h3>
                <?php
                $user_id = get_current_user_id();
                $user_log = get_user_meta($user_id, 'quiz_correct_log', true) ?: [];
                if (!empty($user_log)) {
                    $total_attempts = count($user_log);
                    $correct_count = array_sum($user_log);
                    $accuracy = round(($correct_count / $total_attempts) * 100, 1);
                    echo "<p>挑戦したクイズ数: <strong>{$total_attempts}問</strong></p>";
                    echo "<p>正解数: <strong>{$correct_count}問</strong></p>";
                    echo "<p>正答率: <strong>{$accuracy}%</strong></p>";
                } else {
                    echo "<p>まだクイズに挑戦していません。</p>";
                }
                ?>
            </div>
        <?php endif; ?>
        
        <div class="quizmaker-stats-section">
            <h3 class="quizmaker-stats-title">🏆 人気クイズランキング</h3>
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
                    $url = quizmaker_get_detail_url($quiz_id);
                    echo "<div class='quizmaker-ranking-item'>";
                    echo "<span class='quizmaker-ranking-number'>{$rank}位</span> ";
                    echo "<div class='quizmaker-ranking-title'><a href='".esc_url($url)."'>" . esc_html($quiz->post_title) . "</a></div>";
                    echo "<div class='quizmaker-ranking-meta'>挑戦者: {$stats['attempts']}人 | 正答率: {$accuracy}%</div>";
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
