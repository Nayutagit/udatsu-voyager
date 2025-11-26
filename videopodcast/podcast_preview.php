<?php
$mode = $_GET['mode'] ?? 'both'; // vertical, horizontal, both
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プレビューパネル</title>
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
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 10px;
        }
        
        /* プレビューコンテナ - 比率固定の親要素 */
        .preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            max-width: 100%;
            max-height: 100%;
        }
        
        /* 縦画面プレビュー（9:16 = 0.5625比率固定） */
        .preview-vertical {
            width: 200px;
            aspect-ratio: 9 / 16;
            background: linear-gradient(135deg, #3a3a3a 0%, #555555 50%, #4d4d4d 100%);
            border: 3px solid #fcc800;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.4);
            flex-shrink: 0;
        }
        
        /* 横画面プレビュー（16:9 = 1.7778比率固定） */
        .preview-horizontal {
            width: 320px;
            aspect-ratio: 16 / 9;
            background: linear-gradient(135deg, #3a3a3a 0%, #555555 50%, #4d4d4d 100%);
            border: 3px solid #fcc800;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 0 30px rgba(252, 200, 0, 0.4);
            flex-shrink: 0;
        }
        
        /* 利用可能な空間に合わせてサイズ調整（比率は維持） */
        @container (max-width: 400px) {
            .preview-vertical {
                width: 150px;
            }
            .preview-horizontal {
                width: 240px;
            }
        }
        
        @container (max-height: 500px) {
            .preview-vertical {
                width: 120px;
            }
            .preview-horizontal {
                width: 200px;
            }
            .preview-container {
                gap: 10px;
            }
        }
        
        /* フェイスカム */
        .face-cam-space {
            position: absolute;
            top: 8px;
            left: 8px;
            width: 12%;
            aspect-ratio: 1;
            max-width: 50px;
            max-height: 50px;
            min-width: 20px;
            min-height: 20px;
            border: 2px solid #fcc800;
            border-radius: 8px;
            background: rgba(252, 200, 0, 0.1);
            box-shadow: 0 0 20px rgba(252, 200, 0, 0.5);
            z-index: 1000;
            overflow: hidden;
        }
        
        .face-cam-space video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }
        
        .camera-placeholder {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fcc800;
            font-size: 0.5em;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
            text-align: center;
            z-index: 10;
            white-space: nowrap;
        }
        
        /* スライド表示エリア */
        .slide-viewport {
            width: 100%;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .slide {
            width: 100%;
            height: 100%;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 15% 8% 15% 15%;
            text-align: center;
            position: absolute;
            top: 0;
            left: 0;
        }
        
        .slide.active {
            display: flex;
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* 横画面用のスライド調整 */
        .preview-horizontal .slide {
            padding: 12% 10% 12% 15%;
        }
        
        /* テキストスタイル - レスポンシブ対応 */
        h1 {
            font-size: 0.9em;
            margin-bottom: 0.3em;
            font-weight: 900;
            color: #fcc800;
            text-shadow: 0 0 30px rgba(252, 200, 0, 0.8);
            line-height: 1.1;
        }
        
        h2 {
            font-size: 0.7em;
            margin-bottom: 0.3em;
            font-weight: 700;
            color: #fcc800;
            text-shadow: 0 0 20px rgba(252, 200, 0, 0.6);
            line-height: 1.2;
        }
        
        .quote {
            font-style: italic;
            font-size: 0.5em;
            margin: 0.3em 0;
            color: #fcc800;
            border-left: 2px solid #fcc800;
            padding-left: 0.5em;
            text-shadow: 0 0 15px rgba(252, 200, 0, 0.6);
            line-height: 1.3;
        }
        
        .highlight {
            color: #fcc800;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(252, 200, 0, 0.8);
        }
        
        .main-text {
            font-size: 0.45em;
            margin: 0.3em 0;
            line-height: 1.4;
            color: #e0e0e0;
            text-shadow: 0 0 5px rgba(255, 255, 255, 0.2);
        }
        
        .glow-box {
            background: rgba(252, 200, 0, 0.1);
            border: 1px solid #fcc800;
            border-radius: 6px;
            padding: 0.4em;
            margin: 0.3em 0;
            box-shadow: 0 0 15px rgba(252, 200, 0, 0.3);
            width: 100%;
        }
        
        /* 横画面でのテキスト調整 */
        .preview-horizontal h1 {
            font-size: 1.1em;
        }
        
        .preview-horizontal h2 {
            font-size: 0.9em;
        }
        
        .preview-horizontal .main-text {
            font-size: 0.6em;
        }
        
        .preview-horizontal .quote {
            font-size: 0.65em;
        }
        
        .animated-element {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.6s ease-out;
        }
        
        .animated-element.show {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* ナビゲーションヒント */
        .navigation-hint {
            position: absolute;
            bottom: 8px;
            left: 50%;
            transform: translateX(-50%);
            color: #fcc800;
            font-size: 0.4em;
            animation: pulse 2s infinite;
            white-space: nowrap;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.6; }
            50% { opacity: 1; }
        }
        
        /* タイトル表示 */
        .preview-title {
            position: absolute;
            top: 4px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: #fcc800;
            padding: 2px 6px;
            font-size: 0.5em;
            border-radius: 8px;
            font-weight: bold;
            z-index: 2000;
            white-space: nowrap;
        }
        
        /* 横並び表示（画面に余裕がある場合） */
        @media (min-width: 600px) and (min-height: 400px) {
            .preview-container {
                flex-direction: row;
                gap: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="preview-container">
        <!-- 縦画面プレビュー -->
        <div class="preview-vertical">
            <div class="preview-title">📱 縦画面 (9:16)</div>
            <div class="face-cam-space">
                <video id="verticalVideo" autoplay muted playsinline style="display: none;"></video>
                <div class="camera-placeholder" id="verticalPlaceholder">CAM</div>
            </div>
            
            <div class="slide-viewport" id="verticalSlides">
                <!-- スライドがここに生成される -->
            </div>
        </div>
        
        <!-- 横画面プレビュー -->
        <div class="preview-horizontal">
            <div class="preview-title">🖥️ 横画面 (16:9)</div>
            <div class="face-cam-space">
                <video id="horizontalVideo" autoplay muted playsinline style="display: none;"></video>
                <div class="camera-placeholder" id="horizontalPlaceholder">CAM</div>
            </div>
            
            <div class="slide-viewport" id="horizontalSlides">
                <!-- スライドがここに生成される -->
            </div>
        </div>
    </div>

    <script>
        // グローバル変数
        let currentSlide = 0;
        let currentElement = 0;
        let slides = [];
        let totalSlides = 1;
        let localStream = null;
        let isFlipped = false;
        let brightness = 0;
        let contrast = 100;
        let zoom = 1.0;

        // デフォルトスライド
        const defaultSlide = {
            type: 'intro',
            title: '音声文字起こしからスライド生成',
            content: '左側に文字起こしテキストを入力してAI生成ボタンを押してください',
            note: 'ビデオポッドキャスト用のスライドが自動で作成されます'
        };

        // 初期化
        function initializeSlides() {
            // 縦画面用
            const verticalContainer = document.getElementById('verticalSlides');
            if (verticalContainer) {
                verticalContainer.innerHTML = '';
                const verticalSlide = createSlideElement(defaultSlide, 0);
                verticalContainer.appendChild(verticalSlide);
            }
            
            // 横画面用
            const horizontalContainer = document.getElementById('horizontalSlides');
            if (horizontalContainer) {
                horizontalContainer.innerHTML = '';
                const horizontalSlide = createSlideElement(defaultSlide, 0);
                horizontalContainer.appendChild(horizontalSlide);
            }
            
            updateSlideDisplay();
        }

        // スライド要素作成
        function createSlideElement(slide, index) {
            const slideDiv = document.createElement('div');
            slideDiv.className = `slide${index === 0 ? ' active' : ''}`;
            slideDiv.setAttribute('data-slide', index + 1);
            
            let content = '';
            
            if (slide.type === 'title') {
                content = `
                    <h1 class="animated-element">${slide.title}</h1>
                    <div class="quote animated-element">${slide.subtitle}</div>
                `;
            } else if (slide.type === 'points') {
                const pointsHtml = slide.points.map(point => 
                    `<div class="main-text animated-element">▶ ${point}</div>`
                ).join('');
                
                content = `
                    <h2 class="animated-element">${slide.title}</h2>
                    <div class="glow-box animated-element">
                        ${pointsHtml}
                        <div class="highlight animated-element" style="margin-top: 0.3em;">${slide.summary}</div>
                    </div>
                `;
            } else if (slide.type === 'quote') {
                content = `
                    <h2 class="animated-element">${slide.title}</h2>
                    <div class="glow-box animated-element">
                        <div class="quote animated-element">"${slide.quote}"</div>
                        <div class="highlight animated-element">- ${slide.speaker}</div>
                    </div>
                `;
            } else if (slide.type === 'simple') {
                content = `
                    <h2 class="animated-element">${slide.title}</h2>
                    <div class="glow-box animated-element">
                        <div class="main-text animated-element">${slide.message}</div>
                    </div>
                `;
            } else if (slide.type === 'intro') {
                content = `
                    <h1 class="animated-element">${slide.title}</h1>
                    <div class="main-text animated-element">${slide.content}</div>
                    <div class="glow-box animated-element">
                        <div style="font-size: 0.8em; color: #fcc800;">${slide.note}</div>
                    </div>
                    <div class="navigation-hint">設定パネルからスライドを生成してください 🚀</div>
                `;
            }
            
            slideDiv.innerHTML = content;
            return slideDiv;
        }

        // スライド表示更新
        function updateSlideDisplay() {
            const verticalSlides = document.querySelectorAll('#verticalSlides .slide');
            const horizontalSlides = document.querySelectorAll('#horizontalSlides .slide');
            
            // 縦画面更新
            verticalSlides.forEach((slide, index) => {
                slide.classList.toggle('active', index === currentSlide);
                if (index === currentSlide) {
                    resetAnimations(slide);
                    setTimeout(() => showFirstElement(slide), 100);
                }
            });
            
            // 横画面更新
            horizontalSlides.forEach((slide, index) => {
                slide.classList.toggle('active', index === currentSlide);
                if (index === currentSlide) {
                    resetAnimations(slide);
                    setTimeout(() => showFirstElement(slide), 100);
                }
            });
        }

        // アニメーション制御
        function resetAnimations(slide) {
            if (!slide) return;
            const elements = slide.querySelectorAll('.animated-element');
            elements.forEach(el => el.classList.remove('show'));
            currentElement = 0;
        }

        function showFirstElement(slide) {
            if (!slide) return;
            const elements = slide.querySelectorAll('.animated-element');
            if (elements.length > 0) {
                elements[0].classList.add('show');
                currentElement = 1;
            }
        }

        function showNextElement() {
            const verticalSlide = document.querySelector('#verticalSlides .slide.active');
            const horizontalSlide = document.querySelector('#horizontalSlides .slide.active');
            
            [verticalSlide, horizontalSlide].forEach(slide => {
                if (!slide) return;
                const elements = slide.querySelectorAll('.animated-element');
                if (currentElement < elements.length) {
                    elements[currentElement].classList.add('show');
                }
            });
            
            if (currentElement < document.querySelectorAll('.animated-element').length) {
                currentElement++;
                return true;
            }
            return false;
        }

        // スライド置き換え
        function replaceAllSlides(slideData) {
            slides = slideData;
            totalSlides = slides.length;
            currentSlide = 0;
            currentElement = 0;
            
            // 縦画面用スライド生成
            const verticalContainer = document.getElementById('verticalSlides');
            if (verticalContainer) {
                verticalContainer.innerHTML = '';
                slideData.forEach((slide, index) => {
                    const slideElement = createSlideElement(slide, index);
                    verticalContainer.appendChild(slideElement);
                });
            }
            
            // 横画面用スライド生成
            const horizontalContainer = document.getElementById('horizontalSlides');
            if (horizontalContainer) {
                horizontalContainer.innerHTML = '';
                slideData.forEach((slide, index) => {
                    const slideElement = createSlideElement(slide, index);
                    horizontalContainer.appendChild(slideElement);
                });
            }
            
            updateSlideDisplay();
        }

        // ナビゲーション
        function nextSlide() {
            const hasMoreElements = showNextElement();
            if (!hasMoreElements) {
                currentSlide = (currentSlide + 1) % totalSlides;
                updateSlideDisplay();
            }
        }

        function prevSlide() {
            currentSlide = ((currentSlide - 1) % totalSlides + totalSlides) % totalSlides;
            updateSlideDisplay();
        }

        function resetPresentation() {
            currentSlide = 0;
            updateSlideDisplay();
        }

        // カメラ制御
        function updateCamera(stream, settings = {}) {
            localStream = stream;
            
            const verticalVideo = document.getElementById('verticalVideo');
            const horizontalVideo = document.getElementById('horizontalVideo');
            const verticalPlaceholder = document.getElementById('verticalPlaceholder');
            const horizontalPlaceholder = document.getElementById('horizontalPlaceholder');
            
            if (stream) {
                // カメラ映像を表示
                [verticalVideo, horizontalVideo].forEach(video => {
                    if (video) {
                        video.srcObject = stream;
                        video.style.display = 'block';
                    }
                });
                
                [verticalPlaceholder, horizontalPlaceholder].forEach(placeholder => {
                    if (placeholder) {
                        placeholder.style.display = 'none';
                    }
                });
                
                // 映像調整を適用
                applyVideoFilters(settings);
                
            } else {
                // カメラ停止
                [verticalVideo, horizontalVideo].forEach(video => {
                    if (video) {
                        video.srcObject = null;
                        video.style.display = 'none';
                    }
                });
                
                [verticalPlaceholder, horizontalPlaceholder].forEach(placeholder => {
                    if (placeholder) {
                        placeholder.style.display = 'block';
                        placeholder.innerHTML = 'CAM';
                    }
                });
            }
        }

        function applyVideoFilters(settings) {
            const videos = [document.getElementById('verticalVideo'), document.getElementById('horizontalVideo')];
            
            videos.forEach(video => {
                if (!video) return;
                
                const filters = `brightness(${100 + (settings.brightness || 0)}%) contrast(${settings.contrast || 100}%)`;
                const transform = `scaleX(${settings.isFlipped ? -1 : 1}) scale(${settings.zoom || 1})`;
                
                video.style.filter = filters;
                video.style.transform = transform;
            });
        }

        // 親ウィンドウからのメッセージ受信
        window.addEventListener('message', function(event) {
            const data = event.data;
            
            switch(data.type) {
                case 'slideUpdate':
                    if (data.slides) {
                        replaceAllSlides(data.slides);
                    } else if (data.action) {
                        switch(data.action) {
                            case 'next': nextSlide(); break;
                            case 'prev': prevSlide(); break;
                            case 'reset': resetPresentation(); break;
                        }
                    }
                    break;
                    
                case 'cameraUpdate':
                    if (data.status === 'started') {
                        updateCamera(data.stream, {
                            brightness: data.brightness,
                            contrast: data.contrast,
                            zoom: data.zoom,
                            isFlipped: data.isFlipped
                        });
                    } else if (data.status === 'stopped') {
                        updateCamera(null);
                    } else if (data.setting) {
                        // 設定変更
                        const settings = {};
                        settings[data.setting] = data.value;
                        applyVideoFilters(settings);
                    }
                    break;
                    
                case 'editorUpdate':
                    // 編集内容の反映
                    if (data.slideIndex !== undefined && data.content) {
                        updateSlideContent(data.slideIndex, data.content);
                    }
                    break;
            }
        });

        // スライド内容更新
        function updateSlideContent(slideIndex, content) {
            const verticalSlides = document.querySelectorAll('#verticalSlides .slide');
            const horizontalSlides = document.querySelectorAll('#horizontalSlides .slide');
            
            if (verticalSlides[slideIndex]) {
                verticalSlides[slideIndex].innerHTML = content;
            }
            
            if (horizontalSlides[slideIndex]) {
                horizontalSlides[slideIndex].innerHTML = content;
            }
        }

        // 初期化
        window.addEventListener('load', function() {
            console.log('プレビューパネル起動完了');
            initializeSlides();
        });
    </script>
</body>
</html>