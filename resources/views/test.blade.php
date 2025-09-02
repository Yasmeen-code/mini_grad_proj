<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار المترجم التعليمي</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-center mb-8 text-blue-600">اختبار المترجم التعليمي</h1>

        <!-- Authentication Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">المصادقة</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="email" id="email" placeholder="البريد الإلكتروني" value="test@example.com"
                       class="border rounded-lg px-4 py-2">
                <input type="password" id="password" placeholder="كلمة المرور" value="password"
                       class="border rounded-lg px-4 py-2">
            </div>
            <button onclick="login()" class="mt-4 bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600">
                تسجيل الدخول
            </button>
            <div id="auth-status" class="mt-2 text-sm"></div>
        </div>

        <!-- Compiler Test Section -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">اختبار المترجم</h2>
            <textarea id="code-input" rows="6" placeholder="اكتب الكود هنا..."
                      class="w-full border rounded-lg px-4 py-2 mb-4 font-mono">let x= 7;
let y= 66;
print (x + y);</textarea>
            <div class="flex gap-4">
                <button onclick="compileCode()" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600">
                    ترجمة الكود
                </button>
                <button onclick="stepByStep()" class="bg-yellow-500 text-white px-6 py-2 rounded-lg hover:bg-yellow-600">
                    خطوة بخطوة
                </button>
                <button onclick="getExamples()" class="bg-purple-500 text-white px-6 py-2 rounded-lg hover:bg-purple-600">
                    أمثلة
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">النتائج</h2>

            <!-- Output Display -->
            <div id="output-section" class="mb-6 hidden">
                <h3 class="text-lg font-semibold mb-2 text-green-600">ناتج البرنامج</h3>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="font-mono text-lg" id="program-output"></div>
                </div>
            </div>

            <!-- Detailed Results -->
            <div id="results" class="space-y-4">
                <div class="text-gray-500">النتائج ستظهر هنا...</div>
            </div>
        </div>
    </div>

    <script>
        let authToken = null;

        async function login() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;

            try {
                const response = await axios.post('/api/login', { email, password });
                authToken = response.data.token;
                document.getElementById('auth-status').innerHTML =
                    '<span class="text-green-600">تم تسجيل الدخول بنجاح!</span>';
            } catch (error) {
                document.getElementById('auth-status').innerHTML =
                    '<span class="text-red-600">فشل في تسجيل الدخول: ' + error.response.data.error + '</span>';
            }
        }

        async function compileCode() {
            if (!authToken) {
                alert('يرجى تسجيل الدخول أولاً');
                return;
            }

            const code = document.getElementById('code-input').value;

            try {
                const response = await axios.post('/api/compiler/compile', { code }, {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });

                displayResults(response.data, 'ترجمة الكود');
            } catch (error) {
                displayError(error);
            }
        }

        async function stepByStep() {
            if (!authToken) {
                alert('يرجى تسجيل الدخول أولاً');
                return;
            }

            const code = document.getElementById('code-input').value;

            try {
                const response = await axios.post('/api/compiler/step-by-step', { code }, {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });

                displayResults(response.data, 'خطوة بخطوة');
            } catch (error) {
                displayError(error);
            }
        }

        async function getExamples() {
            if (!authToken) {
                alert('يرجى تسجيل الدخول أولاً');
                return;
            }

            try {
                const response = await axios.get('/api/compiler/examples', {
                    headers: { 'Authorization': `Bearer ${authToken}` }
                });

                displayResults(response.data, 'الأمثلة');
            } catch (error) {
                displayError(error);
            }
        }

        function displayResults(data, title) {
            const resultsDiv = document.getElementById('results');
            const outputSection = document.getElementById('output-section');
            const programOutput = document.getElementById('program-output');

            // Extract and display program output if available
            if (data.success && data.data) {
                let output = '';

                // Try to extract output from assembly/machine code
                if (data.data.assembly && data.data.assembly.length > 0) {
                    const lastInstruction = data.data.assembly[data.data.assembly.length - 1];
                    if (lastInstruction.includes('MOV R1,')) {
                        const match = lastInstruction.match(/MOV R1, (\d+)/);
                        if (match) {
                            output = match[1];
                        }
                    }
                }

                // If we have a specific output, show it
                if (output) {
                    programOutput.textContent = output;
                    outputSection.classList.remove('hidden');
                } else {
                    outputSection.classList.add('hidden');
                }
            } else {
                outputSection.classList.add('hidden');
            }

            // Display detailed compilation results
            resultsDiv.innerHTML = `
                <div class="border rounded-lg p-4">
                    <h3 class="font-semibold text-lg mb-2">${title}</h3>
                    <div class="space-y-3">
                        ${data.success ?
                            `<div class="text-green-600 font-semibold">✅ تمت الترجمة بنجاح</div>` :
                            `<div class="text-red-600 font-semibold">❌ فشلت الترجمة</div>`
                        }

                        ${data.data && data.data.compilation_steps ? `
                            <div>
                                <h4 class="font-semibold mb-2">خطوات الترجمة:</h4>
                                <div class="space-y-1 text-sm">
                                    ${data.data.compilation_steps.map(step => `
                                        <div class="bg-gray-50 p-2 rounded">
                                            <span class="font-medium">${step.stage}:</span> ${step.message}
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        ` : ''}

                        ${data.data && data.data.assembly ? `
                            <div>
                                <h4 class="font-semibold mb-2">كود التجميع:</h4>
                                <pre class="bg-gray-100 p-3 rounded text-sm font-mono overflow-x-auto">${data.data.assembly.join('\n')}</pre>
                            </div>
                        ` : ''}

                        ${data.data && data.data.machine_code ? `
                            <div>
                                <h4 class="font-semibold mb-2">الكود الآلي:</h4>
                                <pre class="bg-gray-100 p-3 rounded text-sm font-mono overflow-x-auto">${data.data.machine_code.join('\n')}</pre>
                            </div>
                        ` : ''}

                        ${data.data && data.data.ai_suggestions && data.data.ai_suggestions.length > 0 ? `
                            <div>
                                <h4 class="font-semibold mb-2">اقتراحات الذكاء الاصطناعي:</h4>
                                <ul class="list-disc list-inside space-y-1 text-sm">
                                    ${data.data.ai_suggestions.map(suggestion => `<li>${suggestion}</li>`).join('')}
                                </ul>
                            </div>
                        ` : ''}

                        <details class="mt-4">
                            <summary class="cursor-pointer font-semibold text-blue-600">عرض التفاصيل الكاملة</summary>
                            <pre class="bg-gray-100 p-4 rounded text-sm overflow-x-auto mt-2">${JSON.stringify(data, null, 2)}</pre>
                        </details>
                    </div>
                </div>
            `;
        }

        function displayError(error) {
            const resultsDiv = document.getElementById('results');
            const outputSection = document.getElementById('output-section');

            // Hide output section on error
            outputSection.classList.add('hidden');

            const errorMessage = error.response ? error.response.data : error.message;
            resultsDiv.innerHTML = `
                <div class="border border-red-300 rounded-lg p-4 bg-red-50">
                    <h3 class="font-semibold text-lg mb-2 text-red-600">خطأ</h3>
                    <div class="text-red-700 mb-2">${errorMessage.error || 'حدث خطأ غير متوقع'}</div>
                    ${errorMessage.compilation_steps ? `
                        <div>
                            <h4 class="font-semibold mb-2">خطوات الترجمة:</h4>
                            <div class="space-y-1 text-sm">
                                ${errorMessage.compilation_steps.map(step => `
                                    <div class="bg-red-50 p-2 rounded border border-red-200">
                                        <span class="font-medium">${step.stage}:</span> ${step.message}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                    <details class="mt-4">
                        <summary class="cursor-pointer font-semibold text-red-600">عرض تفاصيل الخطأ</summary>
                        <pre class="text-red-700 text-sm mt-2">${JSON.stringify(errorMessage, null, 2)}</pre>
                    </details>
                </div>
            `;
        }
    </script>
</body>
</html>
