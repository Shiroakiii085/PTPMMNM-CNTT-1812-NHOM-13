<?php

declare(strict_types=1);

// =============================================================================
// XỬ LÝ CÁC HÀNH ĐỘNG WEB (QUICK SWITCH, LOGIN, REGISTER, USER CRUD, EDIT RECORD)
// =============================================================================
$action = $_GET['action'] ?? '';

// Xử lý Chatbot AI (OpenRouter Free Model + Real-time Streaming SSE + Local Knowledge Engine)
if ($action === 'ai_chat' || $action === 'ai_chat_stream') {
    $isStreaming = ($action === 'ai_chat_stream') || !empty($_GET['stream']);
    if ($isStreaming) {
        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    } else {
        header('Content-Type: application/json; charset=utf-8');
    }
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode((string)$rawInput, true) ?: [];
    $userMsg = trim($data['message'] ?? $_POST['message'] ?? '');
    // Cho phép đổi model nhưng KHÔNG nhận API key từ client gửi lên (cố định dùng API key hệ thống)
    $chosenModel = trim($data['model'] ?? $_POST['model'] ?? 'nvidia/nemotron-3.5-lightning:free');
    
    // Đảm bảo model thuộc danh sách các model free thông minh và ổn định ít bị nghẽn
    $allowedModels = [
        'nvidia/nemotron-3.5-lightning:free',
        'google/gemma-4-26b-a4b-it:free',
        'nvidia/nemotron-3-ultra-550b-a55b:free',
        'google/gemma-4-31b-it:free',
        'openrouter/free',
    ];
    if (!in_array($chosenModel, $allowedModels, true)) {
        $chosenModel = 'nvidia/nemotron-3.5-lightning:free';
    }
    
    if ($userMsg === '') {
        if ($isStreaming) {
            echo "data: [DONE]\n\n";
            exit;
        }
        echo json_encode(['error' => 'Vui lòng nhập nội dung câu hỏi!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // API Key cố định do hệ thống quản lý, tuyệt đối không cho phép đổi hoặc nhận từ client
    $serverApiKey = getenv('OPENROUTER_API_KEY') ?: '';
    if (empty($serverApiKey)) {
        $keyFile = SAMPLE_PUBLIC_DIR . '/../../.local/openrouter_api_key.txt';
        if (file_exists($keyFile)) {
            $serverApiKey = trim((string)@file_get_contents($keyFile));
        }
    }
    if (empty($serverApiKey) && file_exists(SAMPLE_PUBLIC_DIR . '/../../.env')) {
        $envContent = (string)@file_get_contents(SAMPLE_PUBLIC_DIR . '/../../.env');
        if (preg_match('/^OPENROUTER_API_KEY\s*=\s*["\']?([^"\'\r\n]+)["\']?/m', $envContent, $m)) {
            $serverApiKey = trim($m[1]);
        }
    }
    $apiKey = $serverApiKey;

    // BỘ LỌC AN TOÀN DUY NHẤT: CHẶN CÁC NỘI DUNG NGUY HIỂM (CHẤT CẤM, TỰ LÀM HẠI BẢN THÂN)
    $dangerCheck = checkDangerousHarmfulQuery($userMsg);
    if ($dangerCheck !== null) {
        if ($isStreaming) {
            echo "data: " . json_encode(['delta' => $dangerCheck, 'source' => 'safety_guard'], JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: [DONE]\n\n";
            exit;
        }
        echo json_encode([
            'reply' => $dangerCheck,
            'source' => 'safety_guard',
            'model' => $chosenModel,
            'has_api_key' => !empty($serverApiKey)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $u = $currentUser;
    $role = $u ? $u['role'] : 'guest';
    $uName = $u ? $u['username'] : 'anon';
    $fullName = $u ? $u['full_name'] : 'Khách vãng lai';
    $dept = $u ? ($u['department'] ?? 'Chưa xác định') : 'Không';
    
    // Kiểm tra an ninh nghiêm ngặt: Bệnh nhân cố tình hỏi xem hồ sơ của người khác (Chống lỗ hổng IDOR)
    if ($role === 'member' || $role === 'guest') {
        $msgLower = function_exists('mb_strtolower') ? mb_strtolower($userMsg, 'UTF-8') : strtolower($userMsg);
        if (
            str_contains($msgLower, 'bn_binh') || 
            str_contains($msgLower, 'bình') || 
            str_contains($msgLower, 'bệnh nhân khác') || 
            str_contains($msgLower, 'người khác') || 
            str_contains($msgLower, 'bệnh án số 3') || 
            str_contains($msgLower, 'bệnh án 3') || 
            str_contains($msgLower, 'bệnh án số 4') || 
            str_contains($msgLower, 'bệnh án 4') || 
            str_contains($msgLower, 'hồ sơ số 3') || 
            str_contains($msgLower, 'hồ sơ 3') || 
            str_contains($msgLower, 'id 3') || 
            str_contains($msgLower, 'id=3') || 
            str_contains($msgLower, 'id 4') || 
            str_contains($msgLower, 'id=4')
        ) {
            $idorWarn = generateLocalAiResponse($userMsg, $currentUser, $mockRecords);
            if ($isStreaming) {
                echo "data: " . json_encode(['delta' => $idorWarn, 'source' => 'safety_guard'], JSON_UNESCAPED_UNICODE) . "\n\n";
                echo "data: [DONE]\n\n";
                exit;
            }
            echo json_encode([
                'reply' => $idorWarn,
                'source' => 'safety_guard',
                'model' => $chosenModel,
                'has_api_key' => !empty($serverApiKey)
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Thu thập danh sách bệnh án được phép xem (Strict ABAC)
    $allowedRecordsInfo = [];
    if ($role === 'member') {
        foreach ($mockRecords as $rec) {
            if (checkRecordOwnerMatch($rec['owner_username'], $uName)) {
                $allowedRecordsInfo[] = "Bệnh án #{$rec['id']}: '{$rec['title']}' | Bác sĩ: {$rec['doctor_name']} | Chẩn đoán: {$rec['diagnosis']} | Nội dung/Thuốc: {$rec['content']} | Ngày tạo: {$rec['created_at']}";
            }
        }
    } elseif ($role === 'doctor') {
        $allowedRecordsInfo[] = "Bác sĩ lâm sàng: Có quyền xem danh sách bệnh án toàn viện và lập hồ sơ chẩn đoán mới cho bệnh nhân (/?view=create_record). Tổng số bệnh án hiện có: " . count($mockRecords);
    } elseif ($role === 'admin' || $role === 'auditor') {
        $allowedRecordsInfo[] = "Quản trị / Kiểm toán: Toàn quyền giám sát hệ thống y tế. Tổng số bệnh án: " . count($mockRecords);
    }
    
    $sysPrompt = "Bạn là Trợ lý AI Thông Minh MediBot, một trợ lý toàn năng, thân thiện và am hiểu sâu rộng về mọi lĩnh vực tri thức: khoa học, công nghệ, lập trình, toán học, lịch sử, văn hóa, đời sống, sức khỏe và y tế.\n\n"
        . "THÔNG TIN NGƯỜI DÙNG HIỆN TẠI:\n"
        . "- Tên đăng nhập: {$uName}\n"
        . "- Họ và tên: {$fullName}\n"
        . "- Vai trò: {$role}\n"
        . "- Khoa/Phòng: {$dept}\n\n"
        . "DỮ LIỆU BỆNH ÁN ĐƯỢC PHÉP TRUY CẬP (STRICT ABAC):\n"
        . (empty($allowedRecordsInfo) ? "Người dùng chưa có bệnh án cá nhân hoặc chưa đăng nhập.\n" : implode("\n", $allowedRecordsInfo) . "\n")
        . "\nQUY TẮC BẢO MẬT BỆNH ÁN (CHỐNG LỖ HỔNG IDOR):\n"
        . "- Bệnh nhân CHỈ ĐƯỢC PHÉP xem bệnh án của chính mình. Tuyệt đối KHÔNG TIẾT LỘ hồ sơ bệnh án của bệnh nhân khác (ví dụ: bn_an không được xem bệnh án của bn_binh hay bệnh án số 3, 4). Khi có yêu cầu xem trộm, hãy từ chối lịch sự và giải thích về quy định bảo mật kiểm soát truy cập đối tượng ABAC/IDOR.\n\n"
        . "QUY TẮC AN TOÀN BẮT BUỘC (NGHIÊM CẤM TRA CỨU MỤC NGUY HIỂM):\n"
        . "- TUYỆT ĐỐI TỪ CHỐI cung cấp thông tin, hướng dẫn hoặc cách thức liên quan đến: chất cấm, ma túy, chất nổ, vũ khí nguy hiểm, hóa chất độc hại, hoặc các hành vi tự làm hại bản thân / tự tử / tự sát. Hãy từ chối kiên quyết và khuyên người dùng tìm kiếm trợ giúp khẩn cấp y tế (115, 111).\n\n"
        . "QUY TẮC TRẢ LỜI CỦA AI:\n"
        . "1. NGUYÊN TẮC ĐIỀU TIẾT DUNG LƯỢNG, TOÀN DIỆN & TRỌN VẸN Ý (ƯU TIÊN BẮT BUỘC SỐ 1):\n"
        . "   - HÃY TRẢ LỜI ĐỦ Ý, CHI TIẾT VÀ SÂU SẮC, phân tích rõ ràng nguyên nhân, cơ chế hoặc dữ liệu cần thiết với bố cục khoa học (kết hợp các dấu gạch đầu dòng - khi liệt kê luận điểm).\n"
        . "   - KIỂM SOÁT DUNG LƯỢNG CHỦ ĐỘNG: Phân bổ dung lượng câu trả lời trong khoảng 300 đến 450 từ (tối đa không quá 550 từ). Hãy luôn chủ động dành không gian dự phòng để viết phần kết luận hoặc tóm lược trọn vẹn.\n"
        . "   - TUYỆT ĐỐI KHÔNG mở thêm nhánh ý mới khi dung lượng đã dài; LUÔN CHỦ ĐỘNG KẾT BÀI bằng câu chốt hoặc lời khuyên hoàn chỉnh. TUYỆT ĐỐI KHÔNG BAO GIỜ để câu văn bị đứt ngang, cụt lửng hay dang dở; luôn kết thúc bằng dấu chấm câu đàng hoàng (., !, ?).\n\n"
        . "2. NGOÀI CÁC MỤC GÂY NGUY HIỂM BỊ CẤM Ở TRÊN VÀ QUY TẮC BẢO VỆ BỆNH ÁN:\n"
        . "   - BẠN ĐƯỢC PHÉP VÀ KHUYẾN KHÍCH TỰ DO, THOẢI MÁI TRA CỨU, GIẢI ĐÁP TOÀN DIỆN MỌI THÔNG TIN NGOÀI LỀ (KHOA HỌC, ĐỜI SỐNG, CÔNG NGHỆ, LẬP TRÌNH, TOÁN HỌC, LỊCH SỬ, VĂN HÓA, ĐỊA LÝ, NGHỆ THUẬT, BÁCH KHOA TOÀN THƯ...) MÀ KHÔNG BỊ BẤT KỲ BỘ LỌC HAY GIỚI HẠN NÀO NGĂN CẢN.\n\n"
        . "3. VỚI CÂU HỎI VỀ HỆ THỐNG MEDIRECORD EMR:\n"
        . "   - Trả lời chính xác và đầy đủ theo các tính năng thực tế: Trang Chủ BV (/?view=landing), Danh Sách Bệnh Án (/?view=records) kèm xem/sửa/xóa có xác nhận Giữ để mở khóa, Lập Bệnh Án Mới (/?view=create_record), Thông Báo Chung (/?view=announcements), Quản Trị Người Dùng (/?view=users), Giám Sát An Ninh & Kiểm Thử 88 ca test (/?view=tester), Trang Cá Nhân (/?view=profile) có đổi mật khẩu với xác nhận Giữ để mở khóa, Nút USER chuyển tài khoản nhanh, Nút 3D Robot Head.\n\n"
        . "4. QUY TẮC ĐỊNH DẠNG TUYỆT ĐỐI:\n"
        . "   - Tuyệt đối KHÔNG dùng thẻ HTML (như <strong>, <b>, <br>, /strong...).\n"
        . "   - Tuyệt đối KHÔNG dùng ký tự markdown phức tạp như **, ###, __.\n"
        . "   - Trình bày bằng văn bản thuần túy tiếng Việt tự nhiên, ngắt dòng rõ ràng và dùng dấu gạch đầu dòng (-) khi liệt kê danh sách.";

    // Tiếp nhận và làm sạch lịch sử hội thoại đa lượt (Multi-turn Conversation History)
    $clientHistory = is_array($data['history'] ?? null) ? $data['history'] : [];
    $cleanHistory = [];
    if (!empty($clientHistory)) {
        $recentSlice = array_slice($clientHistory, -8);
        foreach ($recentSlice as $item) {
            if (!is_array($item)) continue;
            $r = trim((string)($item['role'] ?? ''));
            $c = trim((string)($item['content'] ?? ''));
            if (($r === 'user' || $r === 'assistant') && !empty($c)) {
                if (function_exists('mb_substr')) {
                    $c = mb_substr($c, 0, 1000, 'UTF-8');
                } else {
                    $c = substr($c, 0, 1000);
                }
                $cleanHistory[] = [
                    'role' => $r,
                    'content' => cleanAiResponseText($c)
                ];
            }
        }
    }

    // Ghép cấu trúc messages ngữ cảnh hoàn chỉnh bao gồm system prompt, lịch sử và câu hỏi hiện tại
    $messagesPayload = [
        ['role' => 'system', 'content' => $sysPrompt]
    ];
    foreach ($cleanHistory as $histItem) {
        $messagesPayload[] = [
            'role' => $histItem['role'],
            'content' => $histItem['content']
        ];
    }
    $messagesPayload[] = ['role' => 'user', 'content' => $userMsg];

    $fallbackModels = [
        'nvidia/nemotron-3.5-lightning:free',
        'google/gemma-4-26b-a4b-it:free',
        'nvidia/nemotron-3-ultra-550b-a55b:free',
        'google/gemma-4-31b-it:free',
        'openrouter/free',
    ];

    // PHƯƠNG ÁN 1: STREAMING THỜI GIAN THỰC (SERVER-SENT EVENTS - TỪNG CHỮ HIỆN RA NGAY KHI AI NGHĨ)
    if ($isStreaming) {
        $streamSuccess = false;
        if (!empty($apiKey)) {
            foreach ($fallbackModels as $currentModel) {
                $streamTokenCount = 0;
                $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'model' => $currentModel,
                    'messages' => $messagesPayload,
                    'temperature' => 0.5,
                    'max_tokens' => 1000,
                    'stream' => true
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                    'HTTP-Referer: https://medirecord-emr.abrdns.com',
                    'X-Title: MediRecord EMR Assistant'
                ]);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
                curl_setopt($ch, CURLOPT_TIMEOUT, 25);

                $streamBuffer = '';
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$streamBuffer, &$streamTokenCount) {
                    $streamBuffer .= $chunk;
                    while (($pos = strpos($streamBuffer, "\n")) !== false) {
                        $line = trim(substr($streamBuffer, 0, $pos));
                        $streamBuffer = substr($streamBuffer, $pos + 1);
                        if (str_starts_with($line, 'data: ')) {
                            $payload = substr($line, 6);
                            if ($payload === '[DONE]') {
                                continue;
                            }
                            $json = json_decode($payload, true);
                            $delta = $json['choices'][0]['delta']['content'] ?? '';
                            if ($delta !== '') {
                                $streamTokenCount++;
                                echo "data: " . json_encode(['delta' => $delta, 'source' => 'openrouter'], JSON_UNESCAPED_UNICODE) . "\n\n";
                                @flush();
                            }
                        }
                    }
                    return strlen($chunk);
                });

                curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($streamTokenCount > 0 && $code >= 200 && $code < 300) {
                    $streamSuccess = true;
                    echo "data: [DONE]\n\n";
                    @flush();
                    break;
                }
            }
        }

        if (!$streamSuccess) {
            $fallbackReply = generateLocalAiResponse($userMsg, $currentUser, $mockRecords, empty($apiKey));
            $words = preg_split('/(\s+)/u', $fallbackReply, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($words as $w) {
                if ($w !== '') {
                    echo "data: " . json_encode(['delta' => $w, 'source' => 'local_fallback'], JSON_UNESCAPED_UNICODE) . "\n\n";
                    @flush();
                    usleep(15000);
                }
            }
            echo "data: [DONE]\n\n";
            @flush();
        }
        exit;
    }

    // PHƯƠNG ÁN 2: PHẢN HỒI BATCH JSON TRUYỀN THỐNG (DÀNH CHO CLIENT KHÔNG DÙNG STREAM)
    $aiResponse = '';
    $source = 'openrouter';

    if (!empty($apiKey)) {
        foreach ($fallbackModels as $currentModel) {
            $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $currentModel,
                'messages' => $messagesPayload,
                'temperature' => 0.5,
                'max_tokens' => 1000
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: https://medirecord-emr.abrdns.com',
                'X-Title: MediRecord EMR Assistant'
            ]);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($res && $code >= 200 && $code < 300) {
                $parsed = json_decode($res, true);
                $reply = $parsed['choices'][0]['message']['content'] ?? '';
                if (!empty(trim($reply))) {
                    $aiResponse = trim($reply);
                    break;
                }
            }
        }
    }

    if (empty($aiResponse)) {
        $source = 'local_fallback';
        $aiResponse = generateLocalAiResponse($userMsg, $currentUser, $mockRecords, empty($apiKey));
    }

    $aiResponse = cleanAiResponseText($aiResponse);

    echo json_encode([
        'reply' => $aiResponse,
        'source' => $source,
        'model' => $chosenModel,
        'has_api_key' => !empty($apiKey)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

