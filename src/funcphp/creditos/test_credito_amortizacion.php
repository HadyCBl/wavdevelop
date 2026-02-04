<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/funcphp/func_gen.php';


use Creditos\Utilidades\CreditoAmortizationSystem;

// require_once __DIR__ . '/CreditoAmortizationSystem.php';


// Cambia este valor por un ccodcta válido en tu base de datos para pruebas
// $ccodcta = '0020010100000005'; //PARA Franc
// $ccodcta = '0250010100000202'; //para Germa

try {
    $credito = new CreditoAmortizationSystem($ccodcta);

    // Simula una reestructuración
    $credito->procesaReestructura();

    $plananterior = $credito->tabla_original;
    $nuevasFechas = $credito->newFechas;
    $nuevosMontos = $credito->newMontos;

    echo "<pre>Plan anterior:\n";
    print_r($plananterior);
    echo "</pre>";

    echo "<pre>Nuevas fechas:\n";
    print_r($nuevasFechas);
    echo "</pre>";

    echo "<pre>Nuevos montos:\n";
    print_r($nuevosMontos);
    echo "</pre>";

    echo "Reestructuración procesada correctamente.\n";
    // Puedes agregar más pruebas aquí, por ejemplo:
    // print_r($credito);
} catch (Exception $e) {
    echo "Error al procesar la reestructuración: " . $e->getMessage() . "\n";
}
return;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mini Excel con Selección</title>
    <style>
        table {
            border-collapse: collapse;
        }

        td,
        th {
            border: 1px solid #999;
            padding: 5px;
            min-width: 100px;
            text-align: center;
            user-select: none;
        }

        td {
            cursor: pointer;
        }

        input {
            width: 100%;
            border: none;
            text-align: center;
        }

        td.highlight {
            background: #ffff99;
        }
    </style>
</head>

<body>

    <table id="sheet">
        <thead>
            <tr>
                <th></th>
                <th>A</th>
                <th>B</th>
                <th>C</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th>1</th>
                <td data-cell="A1"></td>
                <td data-cell="B1"></td>
                <td data-cell="C1"></td>
            </tr>
            <tr>
                <th>2</th>
                <td data-cell="A2"></td>
                <td data-cell="B2"></td>
                <td data-cell="C2"></td>
            </tr>
            <tr>
                <th>3</th>
                <td data-cell="A3"></td>
                <td data-cell="B3"></td>
                <td data-cell="C3"></td>
            </tr>
        </tbody>
    </table>

    <script>
        const sheet = document.getElementById("sheet");
        const cellData = {};
        let editingCell = null;
        let activeInput = null;
        let formulaMode = false;

        // Celdas editables
        sheet.addEventListener("click", (e) => {
            if (e.target.tagName === "TD") {
                const cell = e.target;
                const cellName = cell.dataset.cell;

                // Si estamos en modo fórmula, insertar referencia
                if (formulaMode && editingCell && activeInput) {
                    activeInput.value += cellName;
                    return;
                }

                // Abrir editor
                editingCell = cell;
                const input = document.createElement("input");
                input.value = cellData[cellName]?.formula || cell.textContent;
                cell.textContent = "";
                cell.appendChild(input);
                input.focus();
                activeInput = input;

                input.addEventListener("input", () => {
                    if (input.value.startsWith("=")) {
                        formulaMode = true;
                    } else {
                        formulaMode = false;
                    }
                });

                input.addEventListener("blur", () => {
                    saveCell(cellName, input.value);
                    formulaMode = false;
                    editingCell = null;
                    activeInput = null;
                });

                input.addEventListener("keydown", (ev) => {
                    if (ev.key === "Enter") input.blur();
                });
            }
        });

        function saveCell(cellName, value) {
            const cell = document.querySelector(`[data-cell="${cellName}"]`);
            if (value.startsWith("=")) {
                cellData[cellName] = {
                    formula: value
                };
                evaluateCell(cellName);
            } else {
                cellData[cellName] = {
                    value: value
                };
                cell.textContent = value;
            }
        }

        function evaluateCell(cellName) {
            const cell = document.querySelector(`[data-cell="${cellName}"]`);
            const formula = cellData[cellName].formula;
            try {
                let expr = formula.substring(1);
                expr = expr.replace(/[A-Z][0-9]+/g, ref => {
                    return getCellValue(ref) || 0;
                });
                const result = eval(expr);
                cellData[cellName].value = result;
                cell.textContent = result;
            } catch {
                cell.textContent = "#ERROR";
            }
        }

        function getCellValue(cellName) {
            if (!cellData[cellName]) return 0;
            if (cellData[cellName].formula) {
                evaluateCell(cellName);
            }
            return cellData[cellName].value || 0;
        }
    </script>

</body>

</html>