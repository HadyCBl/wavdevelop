<?php

// FORMULA PARA:P1  a/(b*c)
$P1 = "<math ><mstyle displaystyle='true'><mfrac><mi>a</mi><mrow><mi>b</mi><mo>&#x22C5;</mo><mi>c</mi></mrow></mfrac></mstyle></math> ";

//FORMULA PARA EL P2 ((a-b))/(c*d+e*f) para mororisdad menor a los 12 meses
$P2 = "<math > <mstyle displaystyle='true'><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>-</mo><mi>b</mi><mo>)</mo></mrow></mrow><mrow><mi>c</mi><mo>&#x22C5;</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>&#x22C5;</mo><mi>f</mi></mrow></mfrac></mstyle></math>";

//formula para el p3 se aplica la misma formula pero se da un castigo de 100% por la morosidad ((a-b))/(c*d+e*f)
$P3 = "<math ><mstyle displaystyle='true'><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>-</mo><mi>b</mi><mo>)</mo></mrow></mrow><mrow><mi>c</mi><mo>&#x22C5;</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>&#x22C5;</mo><mi>f</mi></mrow></mfrac></mstyle></math>";

//aqui va la formula para prestamos castigados p4:((a-b))/((c*d)/2)
$P4 = "<math ><mstyle displaystyle='true'><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>-</mo><mi>b</mi><mo>)</mo></mrow></mrow><mrow><mfrac><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow><mn>2</mn></mfrac></mrow></mfrac></mstyle></math>";

//formula de recuperacion acomulada de cartera a/b esta se recibla en E2 E3 E4 E5 E7 E8 A1 A2 L3 SOLO QUE LO QUE VARIA ES EL PORCENTAJE EN LA META
$P5 = "<math > <mstyle displaystyle='true'><mfrac><mi>a</mi><mi>b</mi></mfrac></mstyle></math>";

//FORMULA DE SOLVENCIA [(a+b)-(c+0.35(d)e+f+g)]/(g+h)
$P6 = "<math ><mstyle displaystyle='true'><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>)</mo></mrow><mo>-</mo><mrow><mo>(</mo><mi>c</mi><mo>+</mo><mn>0.35</mn><mrow><mo>(</mo><mi>d</mi><mo>)</mo></mrow><mi>e</mi><mo>+</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>)</mo></mrow></mrow><mrow><mi>g</mi><mo>+</mo><mi>h</mi></mrow></mfrac></mstyle></math>";

// esta es la formula para los prestamos netos(a-b)/c
$E1 = '<math xmlns="http://www.w3.org/1998/Math/MathML"><mstyle displaystyle="true"><mfrac>  <mrow><mrow><mo>(</mo><mi>a</mi><mo>-</mo><mi>b</mi><mo>)</mo></mrow>  </mrow>  <mi>c</mi></mfrac></mstyle></math>';

// ESTA ES LA FORMULA PARA CREDITO EXTERNO (a+b)/c ESTA SE RECIBLA EN L2
$E6 = '<math xmlns="http://www.w3.org/1998/Math/MathML"><mstyle displaystyle="true"><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>)</mo></mrow></mrow><mi>c</mi></mfrac></mstyle></math>';

//FORMULA PARA capital institucional neto
$E9 = '<math ><mstyle displaystyle="true"><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>)</mo></mrow><mo>-</mo><mrow><mo>(</mo><mi>C</mi><mo>+</mo><mn>0.35</mn><mrow><mo>(</mo><mi>d</mi><mo>)</mo></mrow><mo>+</mo><mi>e</mi><mo>)</mo></mrow></mrow><mi>f</mi></mfrac></mstyle></math>';

//aqui se calcula el capital neo isntituional ((a+b+c))/d
$A3 = '<math ><mstyle displaystyle="true"><mfrac><mrow><mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>)</mo></mrow></mrow><mi>d</mi></mfrac></mstyle></math>';

// formula de ingreso neto (a)/((c+b)/2)
$R1 = '<math ><mstyle displaystyle="true"><mfrac><mrow><mi>a</mi><mo>-</mo><mi>b</mi></mrow><mrow><mrow><mo>(</mo><mfrac><mrow><mi>c</mi><mo>+</mo><mi>d</mi></mrow><mn>2</mn></mfrac><mo>)</mo></mrow></mrow></mfrac></mstyle></math>';

// formula para r2 r3 r4 r6 r9 r10 r11 r12 (a)/((c+b)/2)
$R2 = '<math ><mstyle displaystyle="true"><mfrac><mrow><mi>a</mi></mrow><mrow><mrow><mo>(</mo><mfrac><mrow><mi>b</mi><mo>+</mo><mi>c</mi></mrow><mn>2</mn></mfrac><mo>)</mo></mrow></mrow></mfrac></mstyle></math>';

// ESTA FORMULA SE REPITE EN R5 R7
$R5 = '<math ><mstyle displaystyle="true">  <mfrac><mrow><mi>a</mi><mo>+</mo><mi>b</mi><mo>+</mo><mi>c</mi></mrow><mrow>  <mrow><mo>(</mo><mfrac>  <mrow><mi>d</mi><mo>+</mo>    <mi>e</mi>  </mrow>  <mn>2</mn></mfrac><mo>)</mo>  </mrow></mrow>  </mfrac></mstyle></math>';

//formula de margen bruto (((a+b+c+d+e)-(f+g+h)))/(((i+j)/2)
$R8 = '<math ><mstyle displaystyle="true"><mfrac><mrow><mrow><mo>(</mo><mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>+</mo><mi>c</mi><mo>+</mo><mi>d</mi><mo>+</mo><mi>e</mi><mo>)</mo></mrow><mo>-</mo><mrow><mo>(</mo><mi>f</mi><mo>+</mo><mi>g</mi><mo>+</mo><mi>h</mi><mo>)</mo></mrow><mo>)</mo></mrow></mrow><mrow><mrow><mo>(</mo><mfrac><mrow><mi>i</mi><mo>+</mo><mi>j</mi></mrow><mn>2</mn></mfrac><mo>)</mo></mrow></mrow></mfrac></mstyle></math>';

//aqui se calcula el exedente neto
$R13 = '<math ><mstyle displaystyle="true">  <mfrac><mi>a</mi><mrow><mfrac>  <mrow><mi>b</mi><mo>+</mo><mi>c</mi><mo>+</mo><mi>d</mi><mo>+</mo><mi>e</mi></mrow><mn>2</mn>  </mfrac></mrow>  </mfrac></mstyle></math>';

//inversiones liquidas y activos liquidos
$L1 = '<math xmlns="http://www.w3.org/1998/Math/MathML">
<mstyle displaystyle="true">  <mfrac><mrow>  <mrow><mo>(</mo><mi>a</mi><mo>+</mo><mi>b</mi><mo>-</mo><mi>d</mi><mo>)</mo>  </mrow>    </mrow>    <mrow>      <mi>d</mi>    </mrow>  </mfrac></mstyle></math>';

//FUNCION TOTAL PARA EL CAMPO DE S SE REUTILIZA SIMEPRE LA MISMA FORMULA SOLO CAMBIA EL DATO INGRESADO
$S1 = '<math ><mstyle displaystyle="true"><mrow><mo>(</mo><mfrac><mi>a</mi><mi>b</mi></mfrac><mo>)</mo></mrow><mo>-</mo><mn>1</mn><mo>&#x22C5;</mo><mn>100</mn></mstyle></math>';
