<?php
// File: modules/tagging/barcode_generator.php
// Get text parameter from URL
$default_text = isset($_GET['text']) ? htmlspecialchars($_GET['text']) : 'Default Text';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Barcode Generator</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        #barcode-container {
            margin: 20px 0;
        }
        input {
            padding: 8px;
            width: 80%;
            margin-bottom: 15px;
        }
        button {
            padding: 8px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .download-btn {
            margin-top: 15px;
            background-color: #2196F3;
        }
        .download-btn:hover {
            background-color: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Barcode Generator</h1>
        <div>
            <input type="text" id="text-input" placeholder="Enter text for barcode" value="<?php echo $default_text; ?>">
            <button onclick="generateBarcode()">Generate Barcode</button>
        </div>
        <div id="barcode-container"></div>
        <button id="download-btn" class="download-btn" onclick="downloadBarcode()" style="display:none;">Download Barcode</button>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <script>
        function generateBarcode() {
            const text = document.getElementById('text-input').value;
            const container = document.getElementById('barcode-container');
            
            // Clear previous barcode
            container.innerHTML = '';
            
            // Create SVG element
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.id = 'barcode-svg';
            container.appendChild(svg);
            
            // Generate barcode
            try {
                JsBarcode(svg, text, {
                    format: "CODE128",
                    lineColor: "#000",
                    width: 2,
                    height: 100,
                    displayValue: true
                });
                document.getElementById('download-btn').style.display = 'inline-block';
            } catch (e) {
                container.innerHTML = `<p style="color: red;">Error: ${e.message}</p>`;
                document.getElementById('download-btn').style.display = 'none';
            }
        }
        
        function downloadBarcode() {
            const svg = document.getElementById('barcode-svg');
            const serializer = new XMLSerializer();
            const svgBlob = new Blob([serializer.serializeToString(svg)], {type: 'image/svg+xml'});
            const url = URL.createObjectURL(svgBlob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = 'barcode-' + document.getElementById('text-input').value + '.svg';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        }
        
        // Generate default barcode on page load
        window.onload = generateBarcode;
    </script>
</body>
</html>