<!DOCTYPE html>
<html>
<head>
    <title>Online IDE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
</head>
<body class="p-10 bg-gray-100">
    <h1 class="text-2xl font-bold mb-4">Mini IDE</h1>

    <form id="codeForm" method="POST" action="{{ route('code.analyze') }}">
        @csrf
        <textarea name="code" class="w-full h-40 p-2 border" placeholder="اكتب الكود هنا"></textarea>
        <textarea name="input" class="w-full h-20 p-2 border mt-2" placeholder="Input for the code (if needed)"></textarea>
        <button type="submit" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded">Run</button>
    </form>

    <h2 class="text-xl font-semibold mt-4">Result:</h2>
    <pre id="result" class="p-2 bg-white border mt-2"></pre>

    <h2 class="text-xl font-semibold mt-4">Visualization:</h2>
    <div id="flowchart" class="mt-2"></div>

    <script>
        document.getElementById('codeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            let formData = new FormData(this);

            let res = await fetch(this.action, {
                method: 'POST',
                body: formData
            });

            let data = await res.json();
            document.getElementById('result').textContent = data.output;

            if (data.visualization) {
                mermaid.render('flowchart', data.visualization).then((result) => {
                    document.getElementById('flowchart').innerHTML = result.svg;
                });
            } else {
                document.getElementById('flowchart').innerHTML = '';
            }
        });
    </script>
</body>
</html>
