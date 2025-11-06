<?php
// Mini Project with the Hugging Face Space API URL

define('HF_SPACE_API_URL', 'https://mini-helper-api.hf.space/generate');
define('HF_API_TOKEN', '');

function is_crisis($text) {
    $text = mb_strtolower($text);
    $keywords = [
        'kill myself',
        'end my life',
        'suicide',
        'suicidal',
        'hurt myself',
        'hurt others',
        'hurt someone',
        'kill someone',
        'homicide',
        'overdose',
        'immediate danger'
    ];

    foreach ($keywords as $kw) {
        if (strpos($text, $kw) !== false) {
            return true;
        }
    }
    return false;
}

$suggestion = '';
$error = '';
$isCrisis = false;
$challenge_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $challenge = isset($_POST['challenge']) ? trim($_POST['challenge']) : '';
    $challenge_value = $challenge;

    if ($challenge === '') {
        $error = 'Please describe the challenge first.';
    } else {
        if (is_crisis($challenge)) {
            $isCrisis = true;
            $suggestion = "From your description, this could involve significant risk.\n\n"
                . "For any situation that may involve immediate danger to the patient or others, "
                . "follow your clinic's crisis protocol and contact local emergency services or "
                . "crisis lines immediately.\n\n"
                . "This tool is only for general, non-emergency guidance.";
            } else {
                $payload = [
                    'prompt' => $challenge
                ];
    
                $headers = [
                    'Content-Type: application/json'
                ];
                if (HF_API_TOKEN !== '') {
                    $headers[] = 'Authorization: Bearer ' . HF_API_TOKEN;
                }
    
                $ch = curl_init(HF_SPACE_API_URL);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => json_encode($payload),
                    CURLOPT_TIMEOUT        => 120,
                ]);
    
                $response = curl_exec($ch);
    
                if ($response === false) {
                    $error = 'cURL error: ' . curl_error($ch);
                } else {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $data = json_decode($response, true);
                        if (isset($data['text']) && is_string($data['text'])) {
                            $suggestion = trim($data['text']);
                        } else {
                            $error = 'Unexpected API response format: ' . $response;
                        }
                    } else {
                        $error = 'Hugging Face Space API error (HTTP ' . $httpCode . '): ' . $response;
                    }                    
                }
                curl_close($ch);
            }    
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mental Health Counselor Helper (POC)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f4f5fb;
            margin: 0;
            padding: 0;
            color: #1f2933;
        }
        header {
            background: #243b8a;
            color: white;
            padding: 1.2rem 1.5rem;
        }
        header h1 {
            margin: 0;
            font-size: 1.4rem;
        }
        header p {
            margin: 0.3rem 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        main {
            max-width: 900px;
            margin: 1.5rem auto 2rem;
            padding: 0 1rem;
        }
        .card {
            background: white;
            border-radius: 0.8rem;
            padding: 1.5rem;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
            margin-bottom: 1rem;
        }
        .badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-warning {
            background: #fff3cd;
            color: #8a6d3b;
        }
        .badge-info {
            background: #e0edff;
            color: #1a4fb8;
        }
        textarea {
            width: 100%;
            min-height: 160px;
            padding: 0.75rem;
            border-radius: 0.6rem;
            border: 1px solid #cbd2e1;
            font-size: 0.95rem;
            resize: vertical;
            box-sizing: border-box;
        }
        textarea:focus {
            outline: none;
            border-color: #243b8a;
            box-shadow: 0 0 0 2px rgba(36, 59, 138, 0.1);
        }
        label {
            font-weight: 600;
            font-size: 0.95rem;
            display: block;
            margin-bottom: 0.4rem;
        }
        .hint {
            font-size: 0.8rem;
            color: #6b7280;
            margin-bottom: 0.6rem;
        }
        button {
            background: #243b8a;
            color: white;
            border: none;
            border-radius: 999px;
            padding: 0.65rem 1.4rem;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.15);
        }
        button:hover {
            background: #1c2f70;
        }
        .status {
            margin-top: 0.7rem;
            font-size: 0.85rem;
            color: #4b5563;
        }
        .status.error {
            color: #b91c1c;
        }
        .result-title {
            font-weight: 600;
            margin-bottom: 0.4rem;
        }
        .result-box {
            white-space: pre-wrap;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .pill {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            font-size: 0.75rem;
            margin-bottom: 0.4rem;
        }
        .footer-note {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
<header>
    <h1>Mental Health Counselor Helper (POC)</h1>
    <p>A private tool for licensed clinicians – not for emergencies or direct patient use.</p>
</header>

<main>
    <section class="card">
        <span class="badge badge-warning">Important</span>
        <h2 style="margin-top: 0.7rem; font-size: 1rem;">Safety & scope</h2>
        <p style="font-size: 0.9rem; margin-top: 0.4rem;">
            This prototype is for <strong>educational decision support</strong> by licensed mental health professionals.
            It does <strong>not</strong> provide medical advice, diagnosis, or emergency support.
        </p>
        <p style="font-size: 0.9rem;">
            If a situation may involve immediate danger to a patient or others, follow your clinic’s crisis protocol
            and contact local emergency services or crisis lines. Do <strong>not</strong> rely on this tool in emergencies.
        </p>
    </section>

    <section class="card">
        <span class="badge badge-info">Step 1</span>
        <h2 style="margin-top: 0.7rem; font-size: 1rem;">Describe the clinical challenge</h2>

        <form method="post" action="">
            <label for="challenge">What are you struggling with in this case?</label>
            <div class="hint">
                Please <strong>do not include any real names, addresses, phone numbers, or other identifying details</strong>.
                Focus on the clinical situation and your question.
            </div>
            <textarea
                id="challenge"
                name="challenge"
                placeholder="Example: Adult client with long-term anxiety and recent work burnout; keeps minimizing their own distress and apologizing for 'wasting time' in session. I'm unsure how to deepen the work without overwhelming them..."
            ><?php echo htmlspecialchars($challenge_value); ?></textarea>

            <div style="margin-top: 0.9rem;">
                <button type="submit" id="submit-btn">Get suggestion</button>

                <div class="status" id="loading-status" style="display: none;">
                    Processing your request… please wait.
                </div>

                <?php if ($error): ?>
                    <div class="status error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <?php if ($suggestion): ?>
        <section class="card">
            <div class="pill">
                <?php echo $isCrisis ? 'Crisis notice' : 'Suggestion'; ?>
            </div>
            <div class="result-title">
                <?php echo $isCrisis ? 'Crisis / high-risk guidance' : 'Suggested next steps in session'; ?>
            </div>
            <div class="result-box">
                <?php echo nl2br(htmlspecialchars($suggestion)); ?>
            </div>
            <div class="footer-note">
                Always use your own clinical judgment and supervision when applying any suggestions.
            </div>
        </section>
    <?php endif; ?>
</main>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    var form = document.querySelector("form");
    var submitBtn = document.getElementById("submit-btn");
    var loadingStatus = document.getElementById("loading-status");

    if (!form || !submitBtn || !loadingStatus) return;

    form.addEventListener("submit", function () {
      loadingStatus.style.display = "block";
      submitBtn.disabled = true;
      submitBtn.textContent = "Processing...";
    });
  });
</script>
</body>
</html>
