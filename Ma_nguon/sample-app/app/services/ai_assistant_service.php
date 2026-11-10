<?php

declare(strict_types=1);

// =============================================================================
// TRỢ LÝ AI (HỖ TRỢ SỬ DỤNG WEB & TRA CỨU BỆNH ÁN CÁ NHÂN THEO PHÂN QUYỀN)
// =============================================================================
function cleanAiResponseText(string $text): string {
    $cleaned = strip_tags($text);
    $cleaned = (string)preg_replace('#/(strong|b|i|em|span|div|p|h[1-6]|br)\\b#i', '', $cleaned);
    $cleaned = (string)preg_replace('/(\\*\\*|__)(.*?)\\1/', '$2', $cleaned);
    $cleaned = (string)preg_replace('/(\\*|_)(.*?)\\1/', '$2', $cleaned);
    $cleaned = (string)preg_replace('/^#{1,6}\\s+/m', '', $cleaned);
    $cleaned = str_replace(['`', '<strong>', '</strong>', '<b>', '</b>', '/strong', '/b'], '', $cleaned);
    return trim($cleaned);
}

function evaluateMathExpression(string $expr) {
    preg_match_all('#([0-9]+(?:\\.[0-9]+)?|[+\\-*/()])#', $expr, $matches);
    $tokens = $matches[1] ?? [];
    if (empty($tokens)) return null;

    $outputQueue = [];
    $opStack = [];
    $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

    $prevToken = null;
    foreach ($tokens as $token) {
        if (is_numeric($token)) {
            $outputQueue[] = (float)$token;
        } elseif ($token === '-' && ($prevToken === null || in_array($prevToken, ['+', '-', '*', '/', '('], true))) {
            $outputQueue[] = 0.0;
            $opStack[] = '-';
        } elseif (isset($precedence[$token])) {
            while (!empty($opStack)) {
                $top = end($opStack);
                if (isset($precedence[$top]) && $precedence[$top] >= $precedence[$token]) {
                    $outputQueue[] = array_pop($opStack);
                } else {
                    break;
                }
            }
            $opStack[] = $token;
        } elseif ($token === '(') {
            $opStack[] = '(';
        } elseif ($token === ')') {
            while (!empty($opStack) && end($opStack) !== '(') {
                $outputQueue[] = array_pop($opStack);
            }
            if (!empty($opStack) && end($opStack) === '(') {
                array_pop($opStack);
            }
        }
        $prevToken = $token;
    }
    while (!empty($opStack)) {
        $top = array_pop($opStack);
        if ($top !== '(' && $top !== ')') {
            $outputQueue[] = $top;
        }
    }

    $stack = [];
    foreach ($outputQueue as $item) {
        if (is_numeric($item)) {
            $stack[] = (float)$item;
        } else {
            if (count($stack) < 2) return null;
            $b = array_pop($stack);
            $a = array_pop($stack);
            if ($item === '+') $stack[] = $a + $b;
            elseif ($item === '-') $stack[] = $a - $b;
            elseif ($item === '*') $stack[] = $a * $b;
            elseif ($item === '/') {
                if (abs($b) < 1e-9) return 'DIV_ZERO';
                $stack[] = $a / $b;
            }
        }
    }
    if (count($stack) === 1) {
        return $stack[0];
    }
    return null;
}

function tryCalculateMath(string $query): ?string {
    $clean = trim((string)preg_replace('/^(tính|tính toán|kết quả của|tính giúp|cho biết|bằng bao nhiêu)\\s+/ui', '', $query));
    $clean = trim(str_replace(['?', '!', '=', '"', "'"], '', $clean));
    $clean = trim((string)preg_replace('/(\\bbằng bao nhiêu\\b|\\bla bao nhieu\\b|\\bnhé\\b|\\bạ\\b)/ui', '', $clean));
    $clean = str_replace(['x', 'X', ':'], ['*', '*', '/'], $clean);
    
    if (!preg_match('#[0-9]#', $clean) || !preg_match('#[+\\-*/]#', $clean)) {
        return null;
    }
    
    if (!preg_match('#^[0-9\\s.+\\-*/()]+$#', $clean)) {
        return null;
    }
    
    $res = evaluateMathExpression($clean);
    if ($res === 'DIV_ZERO') {
        return "Phép tính " . $clean . " không thể thực hiện vì không thể chia cho số 0.";
    }
    if ($res !== null) {
        $displayRes = (round($res, 6) == round($res, 0)) ? (string)(int)$res : (string)round($res, 6);
        return "Kết quả của phép tính " . $clean . " = " . $displayRes;
    }
    return null;
}

function fetchWikipediaKnowledge(string $query): ?string {
    $cleanQuery = trim(str_replace(['?', '!', ',', '.', ':', ';', '"', "'"], ' ', $query));
    if (empty($cleanQuery)) return null;

    $stopWords = [
        'cho toi biet ve', 'tim hieu ve', 'thong tin ve', 'hay giai thich ve', 'giai thich ve', 'giai thich',
        'cho tôi biết về', 'tìm hiểu về', 'thông tin về', 'hãy giải thích về', 'giải thích về', 'giải thích',
        'la gi', 'la ai', 'nhu the nao', 'o dau', 'khi nao', 'tai sao', 'the nao', 'ra sao',
        'là gì', 'là ai', 'như thế nào', 'ở đâu', 'khi nào', 'tại sao', 'thế nào', 'ra sao'
    ];
    $searchTerm = $cleanQuery;
    foreach ($stopWords as $sw) {
        $searchTerm = (string)preg_replace('#(^|\\s+)' . preg_quote($sw, '#') . '(\\s+|$)#ui', ' ', $searchTerm);
    }
    $searchTerm = trim((string)preg_replace('#\\s+#u', ' ', $searchTerm));
    $len = function_exists('mb_strlen') ? mb_strlen($searchTerm, 'UTF-8') : strlen($searchTerm);
    if (empty($searchTerm) || $len < 2) {
        $searchTerm = $cleanQuery;
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 4,
            'header' => "User-Agent: MediRecordAssistant/1.0 (medirecord@abrdns.com)\r\n"
        ]
    ]);

    $searchUrl = 'https://vi.wikipedia.org/w/api.php?action=query&list=search&srsearch=' . urlencode($searchTerm) . '&format=json&utf8=1';
    $raw = @file_get_contents($searchUrl, false, $ctx);
    if (!$raw) return null;

    $data = json_decode($raw, true);
    $searchItems = $data['query']['search'] ?? [];
    if (empty($searchItems)) return null;

    $bestItem = $searchItems[0];
    $maxScore = -1;
    $lowerFunc = function_exists('mb_strtolower') ? function($s) { return mb_strtolower($s, 'UTF-8'); } : 'strtolower';
    $searchWords = array_filter(explode(' ', $lowerFunc($searchTerm)));
    foreach ($searchItems as $item) {
        $tLower = $lowerFunc($item['title'] ?? '');
        $snipLower = $lowerFunc(strip_tags($item['snippet'] ?? ''));
        $score = 0;
        foreach ($searchWords as $w) {
            if (strlen($w) >= 2) {
                if (str_contains($tLower, $w)) $score += 5;
                if (str_contains($snipLower, $w)) $score += 2;
            }
        }
        if ($tLower === $lowerFunc($searchTerm)) {
            $score += 20;
        }
        if ($score > $maxScore) {
            $maxScore = $score;
            $bestItem = $item;
        }
    }

    $title = $bestItem['title'] ?? '';
    if (empty($title)) return null;

    $summaryUrl = 'https://vi.wikipedia.org/api/rest_v1/page/summary/' . urlencode(str_replace(' ', '_', $title));
    $raw2 = @file_get_contents($summaryUrl, false, $ctx);
    if (!$raw2) return null;

    $sData = json_decode($raw2, true);
    $extract = $sData['extract'] ?? '';
    if (empty(trim($extract))) return null;

    return cleanAiResponseText($extract);
}

// BỘ LỌC AN TOÀN DUY NHẤT: CHẶN CÁC NỘI DUNG NGUY HIỂM (CHẤT CẤM, TỰ LÀM HẠI BẢN THÂN)
function checkDangerousHarmfulQuery(string $query): ?string {
    $qLower = function_exists('mb_strtolower') ? mb_strtolower($query, 'UTF-8') : strtolower($query);
    
    // 1. Tự làm hại bản thân (Self-harm / Suicide)
    $selfHarmKeywords = [
        'tự tử', 'tu tu', 'tự sát', 'tu sat', 'tự hại', 'tu hai', 
        'làm hại bản thân', 'lam hai ban than', 'hủy hoại bản thân', 'huy hoai ban than',
        'cách chết', 'cach chet', 'muốn chết', 'muon chet', 'kết liễu cuộc sống', 'ket lieu cuoc song',
        'cắt cổ tay', 'cat co tay', 'rạch tay', 'rach tay', 'uống thuốc ngủ tự tử', 'treo cổ', 'nhảy cầu',
        'nhảy lầu', 'cách tự sát', 'cách tự tử', 'tự vẫn', 'tu van'
    ];
    foreach ($selfHarmKeywords as $kw) {
        if (str_contains($qLower, $kw)) {
            return "CẢNH BÁO AN TOÀN:\n\n"
                 . "Hệ thống từ chối cung cấp thông tin hoặc hướng dẫn liên quan đến các hành vi tự làm hại bản thân hoặc tự sát.\n\n"
                 . "Nếu bạn hoặc người thân đang trải qua giai đoạn khó khăn, khủng hoảng tâm lý hoặc có ý nghĩ tiêu cực, xin vui lòng tìm kiếm sự trợ giúp y tế ngay lập tức:\n"
                 . "- Cấp cứu Y tế Toàn quốc: 115\n"
                 . "- Tổng đài Quốc gia Bảo vệ Trẻ em & Tư vấn Tâm lý: 111\n"
                 . "- Đường dây nóng hỗ trợ tâm lý Ngày Mai: 096 306 1414\n"
                 . "- Đến ngay cơ sở y tế hoặc bệnh viện gần nhất để được các bác sĩ và chuyên gia tâm lý hỗ trợ kịp thời.";
        }
    }

    // 2. Chất cấm, ma túy, vũ khí, chất nổ, chất độc hại nguy hiểm (Illicit substances / Weapons / Explosives / Toxins)
    $substanceKeywords = [
        'ma túy', 'ma tuy', 'heroin', 'hêrôin', 'héroin', 'cocain', 'côcain', 'cocaine', 
        'thuốc lắc', 'thuoc lac', 'ma túy đá', 'ma tuy da', 'methamphetamine', 'ketamine', 'ecstasy',
        'cần sa tổng hợp', 'can sa tong hop', 'cỏ mỹ', 'co my', 'chế tạo ma túy', 'che tao ma tuy',
        'nấu ma túy', 'nau ma tuy', 'mua bán ma túy', 'buon ban ma tuy', 'chế tạo bom', 'che tao bom',
        'chế tạo chất nổ', 'che tao chat no', 'chế tạo thuốc nổ', 'che tao thuoc no', 'chế tạo vũ khí',
        'chế tạo súng', 'che tao sung', 'chế súng', 'che sung', 'thuốc nổ dẻo', 'thuốc nổ đen',
        'xyanua', 'cyanide', 'chế tạo độc chất', 'đầu độc', 'dau doc', 'chất độc xyanua', 'ricin',
        'vũ khí sinh học', 'vu khi sinh hoc', 'vũ khí hóa học', 'vu khi hoa hoc'
    ];
    foreach ($substanceKeywords as $kw) {
        if (str_contains($qLower, $kw)) {
            return "CẢNH BÁO AN TOÀN:\n\n"
                 . "Hệ thống từ chối cung cấp thông tin, hướng dẫn hoặc cách thức liên quan đến chất cấm, ma túy, chất nổ, vũ khí hoặc hóa chất độc hại nguy hiểm theo quy định pháp luật và tiêu chuẩn an toàn thông tin.\n\n"
                 . "Nếu bạn cần thông tin về điều trị cai nghiện hoặc tư vấn sức khỏe phòng chống tác hại ma túy, xin vui lòng liên hệ các trung tâm y tế, bệnh viện chuyên khoa hoặc cơ quan chức năng có thẩm quyền.";
        }
    }

    return null;
}

function generateLocalAiResponse(string $msg, ?array $u, array $mockRecords, bool $hasNoApiKey = false): string {
    $msgLower = function_exists('mb_strtolower') ? mb_strtolower($msg, 'UTF-8') : strtolower($msg);
    $msgTrim = trim($msg);
    $role = $u ? $u['role'] : 'guest';
    $uName = $u ? $u['username'] : 'anon';
    $fullName = $u ? $u['full_name'] : 'Khách vãng lai';

    // 0. Kiểm tra an toàn: Tuyệt đối không cho tra cứu các mục gây nguy hiểm (chất cấm, tự làm hại bản thân)
    $dangerCheck = checkDangerousHarmfulQuery($msg);
    if ($dangerCheck !== null) {
        return $dangerCheck;
    }

    // 1. Kiểm tra an ninh nghiêm ngặt: Bệnh nhân cố tình hỏi xem hồ sơ của người khác (Chống lỗ hổng IDOR)
    if ($role === 'member' || $role === 'guest') {
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
            return "CẢNH BÁO BẢO MẬT (CHỐNG RÒ RỈ DỮ LIỆU IDOR):\n\n"
                 . "- Bạn đang đăng nhập với tư cách Bệnh nhân " . $fullName . " (" . $uName . ").\n"
                 . "- Bệnh án số 03 và số 04 thuộc quyền sở hữu của Bệnh nhân Trần Thị Bình (bn_binh).\n"
                 . "- Theo chính sách kiểm soát truy cập dựa trên thuộc tính (ABAC), bạn KHÔNG CÓ QUYỀN xem dữ liệu này.\n"
                 . "- Hệ thống MediRecord EMR bảo vệ nghiêm ngặt quyền riêng tư y tế của bệnh nhân.";
        }
    }

    // 2. Tra cứu bệnh án của chính mình hoặc tóm tắt đơn thuốc / tình trạng sức khỏe
    if (
        str_contains($msgLower, 'bệnh án của tôi') || 
        str_contains($msgLower, 'hồ sơ của tôi') || 
        str_contains($msgLower, 'tôi có những hồ sơ') || 
        str_contains($msgLower, 'tóm tắt đơn thuốc') || 
        str_contains($msgLower, 'tình trạng sức khỏe') || 
        str_contains($msgLower, 'thuốc điều trị') || 
        str_contains($msgLower, 'chẩn đoán của tôi')
    ) {
        if ($role === 'member') {
            if ($uName === 'bn_an') {
                return "THÔNG TIN HỒ SƠ BỆNH ÁN CÁ NHÂN (Bệnh nhân Nguyễn Văn An):\n\n"
                     . "1. Hồ sơ bệnh án #01:\n"
                     . "- Chẩn đoán: Viêm xoang sàng xuất tiết cấp.\n"
                     . "- Bác sĩ phụ trách: BS. Nguyễn Văn Minh.\n"
                     . "- Đơn thuốc: Augmentin 1g (ngày 2 viên), Desloratadine 5mg (ngày 1 viên), Nước muối sinh lý xịt mũi 0.9%.\n"
                     . "- Ghi chú dặn dò: Tái khám sau 7 ngày, giữ ấm vùng cổ họng và đeo khẩu trang y tế.\n\n"
                     . "2. Hồ sơ bệnh án #02:\n"
                     . "- Chẩn đoán: Rối loạn lipid máu hỗn hợp, xơ vữa động mạch cảnh nhẹ.\n"
                     . "- Bác sĩ phụ trách: BS. Nguyễn Văn Minh.\n"
                     . "- Đơn thuốc: Atorvastatin 20mg (uống 1 viên buổi tối), Omega-3 1000mg.\n"
                     . "- Ghi chú: Hạn chế mỡ động vật, tăng cường thể dục 30 phút mỗi ngày.";
            } elseif ($uName === 'bn_binh') {
                return "THÔNG TIN HỒ SƠ BỆNH ÁN CÁ NHÂN (Bệnh nhân Trần Thị Bình):\n\n"
                     . "1. Hồ sơ bệnh án #03:\n"
                     . "- Chẩn đoán: Hen phế quản phế nang bậc 2.\n"
                     . "- Bác sĩ phụ trách: BS. Nguyễn Văn Minh.\n"
                     . "- Đơn thuốc: Bình xịt Seretide Evohaler 25/125mcg (xịt 2 nhát/ngày), Ventolin 100mcg khi khó thở cấp.\n\n"
                     . "2. Hồ sơ bệnh án #04:\n"
                     . "- Chẩn đoán: Viêm dạ dày tá tràng mạn tính kèm khuẩn HP (+).\n"
                     . "- Đơn thuốc: Phác đồ Esomeprazole 40mg + Amoxicillin 1g + Clarithromycin 500mg.";
            } else {
                return "THÔNG BÁO HỒ SƠ: Tài khoản của bạn hiện chưa ghi nhận hồ sơ bệnh án khám chữa bệnh nào trên hệ thống MediRecord.";
            }
        } elseif ($role === 'doctor') {
            return "THÔNG TIN BÁC SĨ (BS. Nguyễn Văn Minh):\n\n"
                 . "- Bạn có quyền xem toàn bộ danh sách bệnh án trên toàn viện.\n"
                 . "- Tổng số bệnh án hiện có trong hệ thống: " . count($mockRecords) . " bệnh án.\n"
                 . "- Bạn có thể truy cập mục 'Lập Bệnh Án' trên thanh menu để tạo hồ sơ khám bệnh mới cho bệnh nhân.";
        } elseif ($role === 'admin' || $role === 'auditor') {
            return "QUYỀN QUẢN TRỊ VIÊN / KIỂM TOÁN VIÊN:\n\n"
                 . "- Bạn có quyền giám sát toàn bộ " . count($mockRecords) . " bệnh án trên hệ thống.\n"
                 . "- Bạn có thể kiểm tra danh sách tại mục 'Danh Sách Bệnh Án' hoặc theo dõi audit logs an ninh tại 'Giám Sát An Ninh & Kiểm Thử'.";
        } else {
            return "Vui lòng đăng nhập vào tài khoản bệnh nhân để tra cứu thông tin bệnh án cá nhân của bạn.";
        }
    }

    // 3. Phép tính toán học (Arithmetic Engine)
    $mathResult = tryCalculateMath($msgTrim);
    if ($mathResult !== null) {
        return $mathResult;
    }

    // 4. Tra cứu Bách khoa toàn thư Wikipedia thời gian thực (Xoá hết mọi rào cản, AI thoải mái tra cứu tri thức)
    $wikiResult = fetchWikipediaKnowledge($msgTrim);
    if ($wikiResult !== null && strlen($wikiResult) > 50) {
        return $wikiResult;
    }

    // 4. Giải thích lỗ hổng IDOR/BOLA và an toàn thông tin
    if (str_contains($msgLower, 'idor') || str_contains($msgLower, 'bola') || str_contains($msgLower, 'lỗ hổng') || str_contains($msgLower, 'broken object')) {
        return "GIẢI THÍCH LỖ HỔNG IDOR (INSECURE DIRECT OBJECT REFERENCES):\n\n"
             . "1. Bản chất lỗ hổng:\n"
             . "- Xảy ra khi hệ thống sử dụng định danh đối tượng (như mã ID bệnh án) do phía máy trạm gửi lên mà không kiểm tra xem người dùng hiện tại có quyền hợp pháp đối với đối tượng đó hay không.\n"
             . "- Bệnh nhân này có thể đổi ID thành ID của bệnh nhân khác để xem trộm hoặc sửa đổi bệnh án trái phép.\n\n"
             . "2. Cơ chế phòng thủ trên MediRecord EMR:\n"
             . "- Hệ thống áp dụng chính sách phân quyền kép RBAC kết hợp ABAC (Attribute-Based Access Control).\n"
             . "- Mỗi thao tác truy cập hồ sơ bệnh án đều phải kiểm tra quyền sở hữu chính chủ (owner_username == current_user) hoặc quyền hạn bác sĩ/admin phụ trách.\n"
             . "- Hệ thống tích hợp sẵn 88 ca kiểm thử bảo mật tự động tại trang 'Giám Sát An Ninh & Kiểm Thử', đảm bảo không còn lỗ hổng rò rỉ dữ liệu.";
    }

    // 5. So sánh RBAC và ABAC / Cơ chế bảo mật ABAC
    if (str_contains($msgLower, 'abac') || str_contains($msgLower, 'rbac') || str_contains($msgLower, 'phân quyền')) {
        return "SO SÁNH MÔ HÌNH PHÂN QUYỀN RBAC VÀ ABAC TRONG Y TẾ:\n\n"
             . "1. RBAC (Role-Based Access Control):\n"
             . "- Phân quyền dựa trên chức vụ cố định (Admin, Doctor, Member, Auditor).\n"
             . "- Nhược điểm: Nếu chỉ dùng RBAC, một bệnh nhân (vai trò Member) có quyền 'Xem bệnh án' sẽ có thể xem được bệnh án của TẤT CẢ bệnh nhân khác (lỗ hổng IDOR).\n\n"
             . "2. ABAC (Attribute-Based Access Control):\n"
             . "- Phân quyền dựa trên các thuộc tính động: Thuộc tính người dùng (User ID, Khoa phòng), thuộc tính tài nguyên (Chủ sở hữu owner_id, Độ mật) và ngữ cảnh (Thời gian, Trạng thái hồ sơ).\n"
             . "- Hệ thống kết hợp cả RBAC và ABAC để xây dựng ma trận phân quyền bảo mật cấp y tế toàn diện.";
    }

    // 6. Giải pháp kỹ thuật khắc phục triệt để IDOR
    if (str_contains($msgLower, 'khắc phục') || str_contains($msgLower, 'phòng chống') || str_contains($msgLower, 'sửa idor') || str_contains($msgLower, 'vá lỗ hổng')) {
        return "CÁC NGUYÊN TẮC KỸ THUẬT KHẮC PHỤC TRIỆT ĐỂ IDOR:\n\n"
             . "1. Kiểm tra quyền sở hữu đối tượng ở tầng máy chủ (Server-side Ownership Check):\n"
             . "- Tuyệt đối không tin cậy tham số do client gửi lên.\n"
             . "- Luôn xác thực: record.owner_username === session.user.username trước khi trả về dữ liệu.\n\n"
             . "2. Sử dụng định danh ngẫu nhiên khó đoán (UUIDv4) thay cho ID tự tăng số nguyên.\n"
             . "3. Ghi vết toàn diện vào Nhật ký An ninh (Audit Logs) mọi truy cập bất thường để kịp thời ngăn chặn.\n"
             . "4. Kiểm thử tự động liên tục (DevSecOps):\n"
             . "- Tích hợp các bộ kịch bản kiểm thử tự động (như bộ 88 ca test của MediRecord EMR) để phát hiện và ngăn chặn lỗ hổng trước khi triển khai.";
    }

    // 7. Quy trình tạo bệnh án mới (Dành cho Bác sĩ)
    if (str_contains($msgLower, 'tạo bệnh án') || str_contains($msgLower, 'lập bệnh án') || str_contains($msgLower, 'thêm bệnh án') || str_contains($msgLower, 'nhập viện')) {
        return "QUY TRÌNH LẬP BỆNH ÁN ĐIỆN TỬ MỚI (DÀNH CHO BÁC SĨ / ADMIN):\n\n"
             . "1. Điều kiện: Đăng nhập tài khoản Bác sĩ (bs_minh) hoặc Quản trị viên (admin_bv).\n"
             . "2. Các bước thực hiện:\n"
             . "- Bước 1: Bấm vào mục 'Lập Bệnh Án' trên thanh điều hướng chính.\n"
             . "- Bước 2: Chọn tài khoản bệnh nhân tiếp nhận (ví dụ bn_an hoặc bn_binh).\n"
             . "- Bước 3: Nhập tiêu đề khám bệnh, mã chẩn đoán lâm sàng ICD-10 và tóm tắt bệnh sử.\n"
             . "- Bước 4: Nhập đơn thuốc điều trị chi tiết và chế độ dặn dò.\n"
             . "- Bước 5: Bấm 'Lưu Hồ Sơ Bệnh Án' để ghi nhận vào hệ thống.";
    }

    // 8. Chỉnh sửa và xóa hồ sơ bệnh án (Hold to Unlock)
    if (str_contains($msgLower, 'xóa bệnh án') || str_contains($msgLower, 'sửa bệnh án') || str_contains($msgLower, 'giữ để mở khóa') || str_contains($msgLower, 'hold to unlock')) {
        return "HƯỚNG DẪN CHỈNH SỬA VÀ XÓA HỒ SƠ BỆNH ÁN:\n\n"
             . "1. Phân quyền thực hiện: Chỉ có Bác sĩ phụ trách hoặc Quản trị viên hệ thống mới có quyền chỉnh sửa hoặc xóa hồ sơ bệnh án.\n\n"
             . "2. Cơ chế xác nhận an toàn 'Giữ để mở khóa' (Hold to Unlock):\n"
             . "- Để ngăn ngừa sai sót hoặc vô tình bấm nhầm khi xóa hồ sơ bệnh án hoặc đổi mật khẩu, hệ thống trang bị nút xác nhận an toàn dạng ổ khóa xoay 360 độ.\n"
             . "- Người dùng cần nhấn và giữ chuột (hoặc chạm giữ trên màn hình cảm ứng) vào biểu tượng ổ khóa trong 1 giây cho đến khi vòng tròn chuyển sang màu xanh lá và mở khóa thành công.";
    }

    // 9. Phác đồ chẩn đoán lâm sàng và nguyên tắc kê đơn thuốc
    if (str_contains($msgLower, 'phác đồ') || str_contains($msgLower, 'chẩn đoán') || str_contains($msgLower, 'kê đơn') || str_contains($msgLower, 'hướng dẫn điều trị')) {
        return "NGUYÊN TẮC CHẨN ĐOÁN VÀ KÊ ĐƠN ĐIỀU TRỊ TẠI MEDIRECORD EMR:\n\n"
             . "1. Nguyên tắc chẩn đoán:\n"
             . "- Dựa trên bằng chứng y học lâm sàng và cận lâm sàng.\n"
             . "- Tuân thủ phân loại quốc tế về bệnh tật ICD-10.\n\n"
             . "2. Nguyên tắc kê đơn an toàn:\n"
             . "- Kê đơn đúng chỉ định, đúng liều lượng, đúng thời gian dùng thuốc.\n"
             . "- Kiểm tra kỹ tiền sử dị ứng thuốc và bệnh lý nền của người bệnh.\n"
             . "- Đối chiếu tương tác thuốc (Drug Interactions) để tránh các phối hợp gây độc tính hoặc giảm hiệu quả điều trị.\n"
             . "- Bác sĩ luôn tư vấn rõ ràng cách dùng, liều lượng và các tác dụng phụ cần theo dõi.";
    }

    // 10. Quản trị người dùng & phân quyền nhân viên y tế / Quyền hạn của Admin
    if (str_contains($msgLower, 'quản trị người dùng') || str_contains($msgLower, 'admin') || str_contains($msgLower, 'thêm bác sĩ') || str_contains($msgLower, 'khóa tài khoản') || str_contains($msgLower, 'quyền admin')) {
        return "HƯỚNG DẪN CHỨC NĂNG QUẢN TRỊ NGƯỜI DÙNG (DÀNH CHO ADMIN):\n\n"
             . "1. Phân quyền tài khoản: Admin có toàn quyền tạo mới, chỉnh sửa thông tin, phân loại vai trò (Bác sĩ, Bệnh nhân, Kiểm toán viên, Quản trị viên) và kích hoạt hoặc khóa tài khoản.\n\n"
             . "2. Cách truy cập: Đăng nhập tài khoản admin_bv -> Chọn mục 'Quản Trị Người Dùng' trên thanh điều hướng.\n\n"
             . "3. Tại trang quản trị:\n"
             . "- Danh sách toàn bộ nhân viên y tế và bệnh nhân kèm trạng thái hoạt động.\n"
             . "- Biểu mẫu thêm mới tài khoản nhân sự với đầy đủ các trường thông tin y tế.";
    }

    // 11. Đăng thông báo bệnh viện
    if (str_contains($msgLower, 'thông báo') || str_contains($msgLower, 'bảng tin') || str_contains($msgLower, 'đăng tin')) {
        return "CHỨC NĂNG THÔNG BÁO CHUNG TRÊN MEDIRECORD EMR:\n\n"
             . "1. Hai chuyên mục thông báo:\n"
             . "- Thông báo Bệnh nhân (Public): Phổ biến lịch khám, hướng dẫn y tế cộng đồng, phòng chống dịch bệnh.\n"
             . "- Thông báo Nội bộ (Internal): Phổ biến thông tri nghiệp vụ dành cho Bác sĩ, Nhân viên y tế và Quản trị viên.\n\n"
             . "2. Đăng thông báo mới: Bác sĩ và Quản trị viên có thể truy cập mục 'Thông Báo Chung' trên navbar để đăng tải thông báo mới cho toàn viện.";
    }

    // 12. Kiểm thử an ninh và chạy bộ 88 ca test tự động
    if (str_contains($msgLower, 'kiểm thử') || str_contains($msgLower, 'tester') || str_contains($msgLower, '88 ca test') || str_contains($msgLower, 'test matrix') || str_contains($msgLower, 'audit log')) {
        return "TRANG GIÁM SÁT AN NINH & KIỂM THỬ BẢO MẬT (88 CA TEST):\n\n"
             . "1. Mục đích: Đảm bảo độ tin cậy tuyệt đối về mặt an toàn thông tin, chứng minh không còn bất kỳ lỗ hổng IDOR/BOLA nào trong hệ thống.\n\n"
             . "2. Cấu trúc bộ 88 ca kiểm thử tự động:\n"
             . "- 24 Kiểm thử ma trận phân quyền (Matrix Tests): Kiểm tra chéo toàn bộ các endpoint /api/profile, /api/notes, /api/announcements, /api/admin/users với mọi vai trò.\n"
             . "- 29 Kiểm thử tích hợp CSDL (Integration Tests): Kiểm tra tính nhất quán dữ liệu, quyền sở hữu dự án, lưu trữ kết quả và so sánh chênh lệch.\n"
             . "- 35 Kiểm thử bảo mật nâng cao (Security Edge Cases): Kiểm tra chống IDOR biến thể, xác thực CSRF, chống brute-force và kiểm soát phiên.";
    }

    // 13. Danh sách các tài khoản thực nghiệm
    if (str_contains($msgLower, 'tài khoản') || str_contains($msgLower, 'mật khẩu') || str_contains($msgLower, 'đăng nhập') || str_contains($msgLower, 'user')) {
        return "DANH SÁCH 5 TÀI KHOẢN MẪU THỰC NGHIỆM TRÊN HỆ THỐNG:\n\n"
             . "1. Bác sĩ chuyên khoa: bs_minh (Mật khẩu: TestPass@2026)\n"
             . "2. Bệnh nhân 01: bn_an (Mật khẩu: TestPass@2026)\n"
             . "3. Bệnh nhân 02: bn_binh (Mật khẩu: TestPass@2026)\n"
             . "4. Quản trị viên BV: admin_bv (Mật khẩu: TestPass@2026)\n"
             . "5. Kiểm thử viên An ninh: tester_sec (Mật khẩu: TestPass@2026)\n\n"
             . "Mẹo nhanh: Bạn có thể nhấn nút tròn [USER] ở góc dưới cùng bên phải màn hình để chuyển đổi nhanh giữa các tài khoản chỉ với 1 cú click chuột mà không cần gõ mật khẩu!";
    }

    // 14. Tổng quan các chức năng trên website MediRecord EMR
    if (str_contains($msgLower, 'chức năng') || str_contains($msgLower, 'hệ thống') || str_contains($msgLower, 'website') || str_contains($msgLower, 'tính năng') || str_contains($msgLower, 'hướng dẫn')) {
        return "CẨM NANG CÁC CHỨC NĂNG CHÍNH TRÊN HỆ THỐNG MEDIRECORD EMR:\n\n"
             . "1. Trang Chủ BV (/?view=landing): Cổng thông tin giới thiệu Bệnh viện Đa khoa Quốc tế MediRecord, các chuyên khoa mũi nhọn, quy trình khám bệnh 4 bước và đội ngũ y bác sĩ.\n\n"
             . "2. Danh Sách Bệnh Án (/?view=records): Tra cứu, lọc hồ sơ bệnh án theo mã, tên bệnh nhân hoặc BHYT. Xem chi tiết hồ sơ bệnh án. Bác sĩ và Admin có quyền chỉnh sửa hoặc xóa hồ sơ với xác nhận an toàn 'Giữ để mở khóa'.\n\n"
             . "3. Lập Bệnh Án Mới (/?view=create_record): Dành cho Bác sĩ và Admin ghi nhận tiền sử, chẩn đoán ICD và kê đơn thuốc cho bệnh nhân.\n\n"
             . "4. Thông Báo Chung (/?view=announcements): Bảng tin phân loại gồm thông báo cho người bệnh và thông báo nghiệp vụ nội bộ.\n\n"
             . "5. Quản Trị Người Dùng (/?view=users): Dành riêng cho Admin quản lý nhân sự, cấp quyền tài khoản và kích hoạt tài khoản.\n\n"
             . "6. Giám Sát An Ninh & Kiểm Thử (/?view=tester): Theo dõi nhật ký an ninh hệ thống và chạy bộ 88 kịch bản kiểm thử bảo mật tự động.\n\n"
             . "7. Trang Cá Nhân (/?view=profile): Bấm vào avatar tài khoản ở góc phải thanh navbar để cập nhật thông tin và đổi mật khẩu với xác nhận 'Giữ để mở khóa'.\n\n"
             . "8. Nút Nổi Tiện Ích:\n"
             . "- Nút tròn [USER] góc dưới bên phải: Đổi tài khoản thử nghiệm 1 chạm.\n"
             . "- Biểu tượng Robot 3D góc dưới cùng bên phải: Bật tắt Trợ lý AI MediBot tương tác trực quan.";
    }

    // 15. Trang cá nhân và đổi mật khẩu
    if (str_contains($msgLower, 'trang cá nhân') || str_contains($msgLower, 'đổi mật khẩu') || str_contains($msgLower, 'hồ sơ') || str_contains($msgLower, 'profile')) {
        return "HƯỚNG DẪN TRANG CÁ NHÂN VÀ ĐỔI MẬT KHẨU AN TOÀN:\n\n"
             . "1. Cách mở trang cá nhân: Bấm vào biểu tượng Avatar tài khoản của bạn ở góc trên bên phải thanh navbar -> Chọn 'Trang Cá Nhân'.\n\n"
             . "2. Xem thông tin cá nhân: Hiển thị tên đăng nhập, họ và tên, vai trò phân quyền, khoa/phòng công tác và email liên hệ.\n\n"
             . "3. Đổi mật khẩu an toàn: Nhập mật khẩu hiện tại, mật khẩu mới (tối thiểu 6 ký tự) và xác nhận lại mật khẩu mới. Sau đó, nhấn và giữ biểu tượng ổ khóa 'Giữ để mở khóa' trong 1 giây để hoàn tất lưu mật khẩu mới.";
    }

    // 16. Chào hỏi và giới thiệu
    if (str_contains($msgLower, 'xin chào') || str_contains($msgLower, 'chào') || str_contains($msgLower, 'hello') || str_contains($msgLower, 'hi') || str_contains($msgLower, 'bạn là ai') || str_contains($msgLower, 'tên bạn là gì')) {
        return "Xin chào " . $fullName . "! Tôi là MediBot - Trợ lý AI Thông Minh của Hệ Thống MediRecord EMR.\n\n"
             . "Tôi có thể hỗ trợ bạn:\n"
             . "- Tra cứu hồ sơ bệnh án cá nhân theo đúng phân quyền bảo mật ABAC (chống IDOR).\n"
             . "- Hướng dẫn sử dụng mọi tính năng trên website: Trang Chủ, Bệnh Án, Thông Báo, Quản Trị, Kiểm Thử 88 ca test.\n"
             . "- Giải toán, tra cứu tri thức bách khoa, giải đáp câu hỏi khoa học, công nghệ và đời sống.\n\n"
             . "Bạn muốn tìm hiểu thông tin gì hôm nay?";
    }

    // 17. Lập trình và Công nghệ thông tin
    if (str_contains($msgLower, 'lập trình') || str_contains($msgLower, 'code') || str_contains($msgLower, 'python') || str_contains($msgLower, 'javascript') || str_contains($msgLower, 'php') || str_contains($msgLower, 'java') || str_contains($msgLower, 'c++') || str_contains($msgLower, 'html') || str_contains($msgLower, 'css') || str_contains($msgLower, 'sql') || str_contains($msgLower, 'git') || str_contains($msgLower, 'docker')) {
        return "TƯ VẤN KIẾN THỨC LẬP TRÌNH VÀ CÔNG NGHỆ:\n\n"
             . "1. Tư duy phát triển phần mềm:\n"
             . "- Chọn ngôn ngữ phù hợp: Python cho AI/khoa học dữ liệu, JavaScript/TypeScript cho Full-stack web, PHP/Laravel cho ứng dụng web nhanh và ổn định, C++/Go/Rust cho hệ thống hiệu năng cao.\n"
             . "- Thực hành Clean Code: Đặt tên biến rõ ràng, tuân thủ nguyên lý SOLID, viết hàm nhỏ gọn chỉ làm một nhiệm vụ duy nhất.\n"
             . "- Quản lý mã nguồn với Git: Chia nhánh (branching) rõ ràng, viết commit message súc tích theo chuẩn Conventional Commits.\n\n"
             . "2. Kiến trúc an toàn thông tin (Security-First):\n"
             . "- Luôn kiểm tra tính hợp lệ của dữ liệu đầu vào (Input Validation) để chống SQL Injection, XSS.\n"
             . "- Áp dụng cơ chế kiểm soát truy cập đối tượng ABAC (Attribute-Based Access Control) để triệt tiêu hoàn toàn lỗ hổng IDOR/BOLA.\n\n"
             . "3. Hệ sinh thái MediRecord EMR:\n"
             . "- Được xây dựng trên nền PHP 8.2+, CSDL PostgreSQL và triển khai container hoá qua Docker trên Render Cloud.";
    }

    // 18. Phương pháp học tập, ngoại ngữ và phát triển bản thân
    if (str_contains($msgLower, 'học tiếng anh') || str_contains($msgLower, 'ngoại ngữ') || str_contains($msgLower, 'học tập') || str_contains($msgLower, 'phát triển bản thân') || str_contains($msgLower, 'ghi nhớ') || str_contains($msgLower, 'pomodoro')) {
        return "PHƯƠNG PHÁP HỌC TẬP VÀ PHÁT TRIỂN KỸ NĂNG HIỆU QUẢ:\n\n"
             . "1. Phương pháp Pomodoro (Quản lý thời gian):\n"
             . "- Tập trung làm việc cao độ trong 25 phút, sau đó nghỉ ngắn 5 phút.\n"
             . "- Sau mỗi 4 chu kỳ Pomodoro, hãy nghỉ dài từ 15 đến 20 phút để não bộ tái tạo năng lượng.\n\n"
             . "2. Kỹ thuật Feynman (Học sâu và ghi nhớ lâu):\n"
             . "- Chọn một chủ đề bạn muốn học.\n"
             . "- Thử giảng giải lại chủ đề đó cho một người chưa biết gì bằng ngôn từ thật đơn giản.\n"
             . "- Phát hiện những điểm bạn bị ngắc ngứ, quay lại tài liệu để lấp đầy lỗ hổng kiến thức.\n\n"
             . "3. Lộ trình chinh phục ngoại ngữ (Tiếng Anh):\n"
             . "- Tạo môi trường tiếp xúc hàng ngày: nghe podcast, xem video tài liệu và đọc tin tức tiếng Anh.\n"
             . "- Học từ vựng theo ngữ cảnh và cụm từ (collocations), không học từ đơn lẻ.\n"
             . "- Luyện nói hàng ngày bằng cách tự độc thoại hoặc tìm bạn luyện tập phản xạ.";
    }

    // 19. Đời sống, thư giãn, giấc ngủ và tinh thần
    if (str_contains($msgLower, 'giấc ngủ') || str_contains($msgLower, 'ngủ ngon') || str_contains($msgLower, 'stress') || str_contains($msgLower, 'căng thẳng') || str_contains($msgLower, 'thư giãn') || str_contains($msgLower, 'tâm lý') || str_contains($msgLower, 'mệt mỏi')) {
        return "LỜI KHUYÊN NÂNG CAO SỨC KHỎE TINH THẦN VÀ GIẤC NGỦ:\n\n"
             . "1. Vệ sinh giấc ngủ (Sleep Hygiene):\n"
             . "- Đi ngủ và thức dậy vào cùng một khung giờ mỗi ngày, kể cả ngày cuối tuần.\n"
             . "- Ngưng sử dụng điện thoại, máy tính ít nhất 45 phút trước khi đi ngủ để giảm tác hại của ánh sáng xanh.\n"
             . "- Giữ phòng ngủ yên tĩnh, thoáng mát và đủ tối (nhiệt độ lý tưởng khoảng 20-24 độ C).\n\n"
             . "2. Giải tỏa căng thẳng (Stress Relief):\n"
             . "- Kỹ thuật thở 4-7-8: Hít vào bằng mũi trong 4 giây, nín thở trong 7 giây, thở ra chậm rãi bằng miệng trong 8 giây.\n"
             . "- Dành 15-30 phút mỗi ngày đi dạo ngoài trời tiếp xúc với thiên nhiên và ánh sáng mặt trời.\n"
             . "- Viết nhật ký xả stress hoặc trò chuyện cùng người thân, bạn bè để chia sẻ cảm xúc.";
    }

    // 20. Tra cứu Bách khoa toàn thư Wikipedia theo thời gian thực (Real-time Encyclopedia)
    $wikiResult = fetchWikipediaKnowledge($msgTrim);
    if ($wikiResult !== null && strlen($wikiResult) > 50) {
        return $wikiResult;
    }

    // 21. Phản hồi mở thông minh và thân thiện cho mọi câu hỏi tự do khác
    return "Về câu hỏi: '" . $msgTrim . "'\n\n"
         . "Tôi là Trợ lý AI MediBot. Tôi luôn sẵn sàng trao đổi cởi mở và tra cứu thông tin cùng bạn về mọi chủ đề từ khoa học, công nghệ, lịch sử, văn hóa, y tế cho đến đời sống thường nhật.\n\n"
         . "Để nhận được câu trả lời chi tiết và chính xác nhất, bạn có thể nêu rõ thêm bối cảnh hoặc gửi từ khóa chính của chủ đề bạn muốn tìm hiểu nhé!";
}

