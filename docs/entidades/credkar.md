# Tabla: credkar

Esta tabla almacena información relacionada con créditos y pagos. A continuación se detallan sus campos:

| Columna        | Tipo de dato   | Descripción / Comentario           |
|--------------- |---------------|------------------------------------|
| CODKAR         | INT            | Llave primaria, autoincrement      |
| CCODCTA        | VARCHAR(20)    | CuentaCredito                      |
| DFECPRO        | DATE           | Fecha Proceso                      |
| DFECSIS        | DATETIME       | FechaSys                           |
| CNROCUO        | INT(4)         | CorrelativoPago                    |
| NMONTO         | DECIMAL(20,2)  | MontoTotal                         |
| CNUMING        | VARCHAR(20)    | No.Boleta/recibo                   |
| CCONCEP        | TEXT           | Concepto                           |
| KP             | DECIMAL(20,2)  | CAPITAL PAGADO                     |
| INTERES        | DECIMAL(20,2)  | INTERES                            |
| MORA           | DECIMAL(20,2)  | MORA                               |
| AHORGP         | DECIMAL(20,2)  | AHORRO PROGRAMADO                  |
| OTR            | DECIMAL(20,2)  | OTROS                              |
| CCODINS        | VARCHAR(3)     | CodInstituto                       |
| CCODOFI        | VARCHAR(3)     | CodOfici                           |
| CCODUSU        | VARCHAR(4)     | Cod_USU                            |
| CTIPPAG        | VARCHAR(3)     | D=dsmlbs/P=pago                    |
| CMONEDA        | VARCHAR(4)     | Moneda_tipo                        |
| FormPago       | VARCHAR(3)     | Revisar nota 2                     |
| DFECBANCO      | DATE           | FechaBoleta                        |
| boletabanco    | VARCHAR(100)   |                                    |
| CBANCO         | VARCHAR(20)    | BANCO_NOMBRE                       |
| CCODBANCO      | VARCHAR(18)    | CuentaBancaria                     |
| CESTADO        | VARCHAR(1)     | ReversionPago                      |
| DFECMOD        | DATE           |                                    |
| CTERMID        | VARCHAR(3)     | Cod_Terminal                       |
| MANCOMUNAD     | INT(10)        | Cod_grup                           |
| updated_by     | INT(4)         | Usuario que actualizó              |
| deleted_by     | INT(4)         | Usuario que eliminó                |
| updated_at     | DATETIME       | Fecha de actualización             |
| deleted_at     | DATETIME       | Fecha de eliminación               |

> Notas:
> - Los campos de auditoría (`updated_by`, `deleted_by`, `updated_at`, `deleted_at`) permiten rastrear cambios y eliminaciones.
> - El campo `FormPago` indica la forma de pago (DESEMBOLSOS[1 EFECTIVO, 2 CHEQUE, 3 TRANSFERENCIAS]), (PAGOS[1 EFECTIVO,2 BOLETA BANCO, 4 CANCELACION POR REFINANCIAMIENTO])
> - Los campos de monto (`NMONTO`, `KP`, `INTERES`, `MORA`, `AHORGP`, `OTR`) están en formato decimal para precisión financiera.
