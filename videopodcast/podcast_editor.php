<?php
session_start();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>編集パネル</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;700;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: transparent;
            color: #ffffff;
            height: 100vh;
            overflow: hidden;
            position: relative;
            margin: 0;
            padding: 0;
        }
        
        /* 編集プレビュー */
        .edit-preview {
            width: 100%;
            height: calc(100vh - 130px);
            padding: 80px 20px 80px 20px;
            background: transparent;
            overflow-y: auto;
            position: relative;
        }
        
        .editable-content {
            width: 100%;
            min-height: calc(100vh - 200px);
            outline: none;
            color: #e0e0e0;
            font-family: 'Noto Sans JP', sans-serif;
            line-height: 1.5;
            display: block;
            text-align: left;
            border: 1px dashed #444;
            border-radius: 8px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.1);
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        
        .editable-content:focus {
            border-color: #fcc800;
            box-shadow: 0 0 10px rgba(252, 200, 0, 0.3);
        }
        
        .editable-content h1 {
            font-size: 1.6em;
            font-weight: 900;
            color: #fcc800;
            text-shadow: 0 0 30px rgba(252, 200, 0, 0.8);
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .editable-content h2 {
            font-size: 1.2em;
            font-weight: 700;
            color: #fcc800;
            text-shadow: 0 0 20px rgba(252, 200, 0, 0.6);
            margin-bottom: 15px;
            line-height: 1.3;
        }
        
        .editable-content .quote {
            font-style: italic;
            font-size: 0.9em;
            color: #fcc800;
            border-left: 4px solid #fcc800;
            padding-left: 12px;
            margin: 15px 0;
            text-shadow: 0 0 15px rgba(252, 200, 0, 0.6);
            line-height: 1.4;
        }
        
        .editable-content .highlight {
            color: #fcc800;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
        }
        
        .editable-content .main-text {
            font-size: 0.85em;
            line-height: 1.5;
            color: #e0e0e0;
            margin: 15px 0;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.2);
        }
        
        .editable-content .glow-box {
            background: rgba(252, 200, 0, 0.1);
            border: 2px solid #fcc800;
            border-radius: 10px;
            padding: 15px;
            margin: 12px 0;
            box-shadow: 0 0 20px rgba(252, 200, 0, 0.3);
            width: 100%;
            max-width: 95%;
        }
        
        /* 編集パネル内のスライド選択 */
        .edit-slide-selector {
            position: absolute;
            bottom: 15px;
            left: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #fcc800;
            border-radius: 8px;
            padding: 10px;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .edit-nav-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .edit-nav-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
        }
        
        .edit-nav-btn:disabled {
            background: rgba(100, 100, 100, 0.5);
            color: #666;
            cursor: not-allowed;
            transform: none;
        }
        
        .edit-slide-info {
            color: #fcc800;
            font-size: 11px;
            font-weight: bold;
            flex-grow: 1;
            text-align: center;
        }
        
        /* ツールバー */
        .edit-toolbar {
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid #fcc800;
            border-radius: 8px;
            padding: 10px;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .toolbar-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toolbar-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
        }
        
        .toolbar-separator {
            width: 1px;
            height: 20px;
            background: #fcc800;
            margin: 0 5px;
        }
        
        /* スタイル適用ボタン */
        .style-buttons {
            position: absolute;
            top: 60px;
            right: 15px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            z-index: 1500;
        }
        
        .style-btn {
            background: rgba(252, 200, 0, 0.8);
            color: #000;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 80px;
        }
        
        .style-btn:hover {
            background: #fcc800;
            transform: scale(1.05);
        }
        
        /* メッセージ表示 */
        .message {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: bold;
            z-index: 10000;
            display: none;
        }
        
        .message.success {
            background: linear-gradient(45deg, #4caf50, #fcc800);
            color: white;
        }
        
        .message.info {
            background: linear-gradient(45deg, #2196f3, #fcc800);
            color: white;
        }
    </style>
</head>
<body>
    <!-- ツールバー -->
    <div class="edit-toolbar">
        <button class="toolbar-btn" onclick="formatText('bold')">B</button>
        <button class="toolbar-btn" onclick="formatText('italic')">I</button>
        <button class="toolbar-btn" onclick="formatText('underline')">U</button>
        <div class="toolbar-separator"></div>
        <button class="toolbar-btn" onclick="clearFormatting()">🧹 クリア</button>
        <button class="toolbar-btn" onclick="undoEdit()">↶ 元に戻す</button>
        <button class="toolbar-btn" onclick="saveEdit()">💾 保存</button>
    </div>
    
    <!-- スタイル適用ボタン -->
    <div class="style-buttons">
        <button class="style-btn" onclick="applyStyle('title')">タイトル</button>
        <button class="style-btn" onclick="applyStyle('subtitle')">サブタイトル</button>
        <button class="style-btn" onclick="applyStyle('highlight')">ハイライト</button>
        <button class="style-btn" onclick="applyStyle('quote')">引用</button>
        <button class="style-btn" onclick="applyStyle('glowbox')">ボックス</button>
    </div>
    
    <!-- 編集プレビュー -->
    <div class="edit-preview">
        <div contenteditable="true" class="editable-content" id="editableContent">
            <h2 style="color: #fcc800; margin-bottom: 15px; text-align: center;">文字起こしテキストを入力して</h2>
            <div class="main-text" style="text-align: center;">AIがスライドを自動生成します</div>
            <div style="background: rgba(252, 200, 0, 0.1); border: 1px solid #fcc800; border-radius: 8px; padding: 15px; margin-top: 15px; text-align: center;">
                <div style="font-size: 0.8em; color: #fcc800;">💡 生成されたスライドはここで編集できます</div>
            </div>
        </div>
    </div>
    
    <!-- 編集パネル内のスライド選択 -->
    <div class="edit-slide-selector">
        <button class="edit-nav-btn" onclick="editPrevSlide()" id="editPrevBtn">⬅️</button>
        <div class="edit-slide-info" id="editSlideInfo">編集: 1/1</div>
        <button class="edit-nav-btn" onclick="editNextSlide()" id="editNextBtn">➡️</button>
    </div>

    <div class="message" id="messageDiv"></div>

    <script>
        // グローバル変数
        let currentEditSlide = 0;
        let totalSlides = 1;
        let slides = [];
        let editHistory = [];
        let historyIndex = -1;

        // 親ウィンドウへの通信
        function sendToParent(type, data) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type, ...data }, '*');
            }
        }

        // 編集履歴の保存
        function saveToHistory() {
            const content = document.getElementById('editableContent').innerHTML;
            editHistory = editHistory.slice(0, historyIndex + 1);
            editHistory.push(content);
            historyIndex++;
            
            // 履歴の上限を20に制限
            if (editHistory.length > 20) {
                editHistory.shift();
                historyIndex--;
            }
        }

        // リアルタイム編集の設定
        function setupRealTimeEdit() {
            const editableContent = document.getElementById('editableContent');
            if (editableContent) {
                // 初期状態を履歴に保存
                saveToHistory();
                
                // あらゆる入力イベントを監視
                const events = ['input', 'keyup', 'paste', 'cut', 'delete', 'compositionend'];
                
                events.forEach(eventType => {
                    editableContent.addEventListener(eventType, function() {
                        setTimeout(() => {
                            updateSlideFromEdit();
                            if (eventType === 'input' || eventType === 'paste' || eventType === 'cut') {
                                saveToHistory();
                            }
                        }, 50);
                    });
                });
                
                // フォーカス時とフォーカス外れた時も更新
                editableContent.addEventListener('focus', updateSlideFromEdit);
                editableContent.addEventListener('blur', () => {
                    updateSlideFromEdit();
                    saveEdit();
                });
            }
        }

        // 編集内容をプレビューに反映
        function updateSlideFromEdit() {
            const editableContent = document.getElementById('editableContent');
            if (!editableContent) return;
            
            const newContent = editableContent.innerHTML;
            
            // 親ウィンドウ（プレビュー）に送信
            sendToParent('editorUpdate', {
                slideIndex: currentEditSlide,
                content: newContent
            });
            
            // スライドデータを更新
            if (slides[currentEditSlide]) {
                slides[currentEditSlide].content = newContent;
            }
        }

        // スライドナビゲーション
        function editNextSlide() {
            if (currentEditSlide < totalSlides - 1) {
                currentEditSlide++;
                updateEditPreview();
                updateEditSlideInfo();
            }
        }

        function editPrevSlide() {
            if (currentEditSlide > 0) {
                currentEditSlide--;
                updateEditPreview();
                updateEditSlideInfo();
            }
        }

        function updateEditSlideInfo() {
            const editSlideInfo = document.getElementById('editSlideInfo');
            const prevBtn = document.getElementById('editPrevBtn');
            const nextBtn = document.getElementById('editNextBtn');
            
            if (editSlideInfo) {
                editSlideInfo.textContent = `編集: ${currentEditSlide + 1}/${totalSlides}`;
            }
            
            // ボタンの有効/無効制御
            if (prevBtn) prevBtn.disabled = currentEditSlide === 0;
            if (nextBtn) nextBtn.disabled = currentEditSlide === totalSlides - 1;
        }

        function updateEditPreview() {
            const editableContent = document.getElementById('editableContent');
            if (!editableContent || !slides[currentEditSlide]) return;
            
            const currentSlide = slides[currentEditSlide];
            if (currentSlide && currentSlide.content) {
                editableContent.innerHTML = currentSlide.content;
            } else {
                // デフォルトコンテンツを生成
                const defaultContent = generateSlideContent(currentSlide);
                editableContent.innerHTML = defaultContent;
            }
            
            // 履歴をリセット
            editHistory = [];
            historyIndex = -1;
            saveToHistory();
        }

        // スライドコンテンツ生成
        function generateSlideContent(slide) {
            if (!slide) return '';
            
            let content = '';
            
            if (slide.type === 'title') {
                content = `
                    <h1>${slide.title}</h1>
                    <div class="quote">${slide.subtitle}</div>
                `;
            } else if (slide.type === 'points') {
                const pointsHtml = slide.points.map(point => 
                    `<div class="main-text">▶ ${point}</div>`
                ).join('');
                
                content = `
                    <h2>${slide.title}</h2>
                    <div class="glow-box">
                        ${pointsHtml}
                        <div class="highlight" style="margin-top: 15px;">${slide.summary}</div>
                    </div>
                `;
            } else if (slide.type === 'quote') {
                content = `
                    <h2>${slide.title}</h2>
                    <div class="glow-box">
                        <div class="quote">"${slide.quote}"</div>
                        <div class="highlight">- ${slide.speaker}</div>
                    </div>
                `;
            } else if (slide.type === 'simple') {
                content = `
                    <h2>${slide.title}</h2>
                    <div class="glow-box">
                        <div class="main-text">${slide.message}</div>
                    </div>
                `;
            }
            
            return content;
        }

        // テキストフォーマット
        function formatText(command) {
            document.execCommand(command, false, null);
            updateSlideFromEdit();
            saveToHistory();
        }

        function clearFormatting() {
            document.execCommand('removeFormat', false, null);
            updateSlideFromEdit();
            saveToHistory();
            showMessage('フォーマットをクリアしました', 'info');
        }

        function undoEdit() {
            if (historyIndex > 0) {
                historyIndex--;
                const editableContent = document.getElementById('editableContent');
                editableContent.innerHTML = editHistory[historyIndex];
                updateSlideFromEdit();
                showMessage('元に戻しました', 'info');
            }
        }

        function saveEdit() {
            updateSlideFromEdit();
            showMessage('編集内容を保存しました', 'success');
        }

        // スタイル適用
        function applyStyle(styleType) {
            const selection = window.getSelection();
            if (selection.rangeCount === 0) {
                showMessage('テキストを選択してからスタイルを適用してください', 'info');
                return;
            }
            
            const range = selection.getRangeAt(0);
            const selectedText = range.toString();
            
            if (!selectedText) {
                showMessage('テキストを選択してください', 'info');
                return;
            }
            
            let wrappedContent = '';
            
            switch(styleType) {
                case 'title':
                    wrappedContent = `<h1>${selectedText}</h1>`;
                    break;
                case 'subtitle':
                    wrappedContent = `<h2>${selectedText}</h2>`;
                    break;
                case 'highlight':
                    wrappedContent = `<span class="highlight">${selectedText}</span>`;
                    break;
                case 'quote':
                    wrappedContent = `<div class="quote">${selectedText}</div>`;
                    break;
                case 'glowbox':
                    wrappedContent = `<div class="glow-box">${selectedText}</div>`;
                    break;
            }
            
            range.deleteContents();
            const newNode = document.createElement('div');
            newNode.innerHTML = wrappedContent;
            range.insertNode(newNode.firstChild);
            
            selection.removeAllRanges();
            updateSlideFromEdit();
            saveToHistory();
            showMessage(`${styleType}スタイルを適用しました`, 'success');
        }

        // スライドデータの更新
        function updateSlides(newSlides) {
            slides = newSlides;
            totalSlides = slides.length;
            currentEditSlide = 0;
            
            updateEditPreview();
            updateEditSlideInfo();
            
            showMessage(`${totalSlides}枚のスライドを読み込みました`, 'success');
        }

        // メッセージ表示
        function showMessage(message, type) {
            const messageDiv = document.getElementById('messageDiv');
            messageDiv.textContent = message;
            messageDiv.className = `message ${type}`;
            messageDiv.style.display = 'block';
            
            setTimeout(() => {
                messageDiv.style.display = 'none';
            }, 2000);
        }

        // 親ウィンドウからのメッセージ受信
        window.addEventListener('message', function(event) {
            const data = event.data;
            
            switch(data.type) {
                case 'slideUpdate':
                    if (data.slides) {
                        updateSlides(data.slides);
                    }
                    break;
                    
                case 'refresh':
                    location.reload();
                    break;
            }
        });

        // キーボードショートカット
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'b':
                        e.preventDefault();
                        formatText('bold');
                        break;
                    case 'i':
                        e.preventDefault();
                        formatText('italic');
                        break;
                    case 'u':
                        e.preventDefault();
                        formatText('underline');
                        break;
                    case 'z':
                        e.preventDefault();
                        undoEdit();
                        break;
                    case 's':
                        e.preventDefault();
                        saveEdit();
                        break;
                }
            }
            
            // スライドナビゲーション（編集エリア以外）
            const editableContent = document.getElementById('editableContent');
            if (editableContent !== document.activeElement) {
                switch(e.key) {
                    case 'ArrowRight':
                        e.preventDefault();
                        editNextSlide();
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        editPrevSlide();
                        break;
                }
            }
        });

        // 初期化
        window.addEventListener('load', function() {
            console.log('編集パネル起動完了');
            setupRealTimeEdit();
            updateEditSlideInfo();
        });

        // ページ離脱前の確認
        window.addEventListener('beforeunload', function(e) {
            // 未保存の変更がある場合の警告
            if (editHistory.length > 1) {
                e.preventDefault();
                e.returnValue = '編集中の内容があります。本当にページを離れますか？';
                return e.returnValue;
            }
        });
    </script>
</body>
</html>