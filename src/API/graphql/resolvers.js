require('dotenv').config();

const { primaryPool, secondaryPool } = require("../config/db");
const jwt = require("jsonwebtoken");
const http = require("http");
const https = require("https");
const url = require("url");

// script PHP
const PHP_DECRYPT_URL = `${process.env.HOST || 'https://pruebas.sotecprotech.com/'}/src/decrypt.php`;

/**
 * Función petición HTTP POST
 */
function httpPost(urlString, data) {
  return new Promise((resolve, reject) => {
    const parsedUrl = url.parse(urlString);
    const options = {
      hostname: parsedUrl.hostname,
      port: parsedUrl.port || (parsedUrl.protocol === "https:" ? 443 : 80),
      path: parsedUrl.path,
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Content-Length": Buffer.byteLength(JSON.stringify(data)),
      },
    };

    const requester = parsedUrl.protocol === "https:" ? https : http;

    const req = requester.request(options, (res) => {
      let responseData = "";

      res.on("data", (chunk) => {
        responseData += chunk;
      });

      res.on("end", () => {
        try {
          resolve(responseData.trim().replace(/"/g, ""));
        } catch (error) {
          reject(new Error("Error al parsear la respuesta: " + error.message));
        }
      });
    });

    req.on("error", (error) => {
      reject(error);
    });

    req.write(JSON.stringify(data));
    req.end();
  });
}

/**
 * Función que desencripta
 */
async function desencriptarConPHP(textoEncriptado) {
  try {
    const response = await httpPost(PHP_DECRYPT_URL, {
      action: "decrypt",
      value: textoEncriptado,
    });

    if (response) {
      return response;
    } else {
      throw new Error("Error desconocido al desencriptar");
    }
  } catch (error) {
    console.error("Error al llamar al script PHP:", error.message);
    throw new Error("Error al desencriptar: " + error.message);
  }
}

/**
 * Manejo de valores vacíos o nulos
 */
function handleEmptyValue(value) {
  return value == null || value === "" ? "No obtenido" : value;
}

// Resolvers para las consultas y mutaciones de GraphQL
// Estos resolvers manejan las consultas y mutaciones definidas en el esquema GraphQL
//----------------------------------------------------------------------------------
//---------------PARTE DE QUERYS----------------------------------------------------
//----------------------------------------------------------------------------------

const resolvers = {
  Query: {
    licenciaDatos: async (
      _,
      { uniqueId, manufacturer, model, modelName, deviceType },
      { primaryPool }
    ) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(
          "SELECT * FROM pos_licencias WHERE uniqueId = ? AND manufacturer = ? AND model = ? AND modelName = ? AND deviceType = ?",
          [uniqueId, manufacturer, model, modelName, deviceType]
        );

        console.log("Resultado de la consulta pos_licencias:", rows);

        if (!rows || rows.length === 0) {
          return {
            id: 0,
            uniqueId,
            manufacturer,
            model,
            modelName,
            deviceType,
            estado: "Dispositivo no registrado",
          };
        }

        const lic = rows[0];
        if (!lic) {
          return {
            id: 0,
            uniqueId,
            manufacturer,
            model,
            modelName,
            deviceType,
            estado: "Dispositivo no registrado",
          };
        }

        const estado = lic.state === 1 ? "activo" : "desactivado";

        return {
          id: lic.id,
          uniqueId: lic.uniqueId,
          modelName: lic.modelName,
          manufacturer: lic.manufacturer,
          model: lic.model,
          deviceType: lic.deviceType,
          estado,
        };
      } catch (error) {
        console.error(
          "Error al consultar por características del dispositivo:",
          error.message
        );
        throw new Error("Error al consultar la licencia por características");
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    searchCredits: async (_, __, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(`
          SELECT 
            cm.CCODCTA AS ccodcta, 
            cl.short_name AS nombre, 
            cm.MonSug AS monsug, 
            SUM(CASE WHEN ck.CTIPPAG = 'P' AND ck.CESTADO != 'X' THEN ck.KP ELSE 0 END) AS Saldo,
            MAX(ck.CTIPPAG) AS estado,
            MAX(ck.CESTADO) AS eliminado
          FROM 
            cremcre_meta cm 
          INNER JOIN 
            tb_cliente cl ON cm.CodCli = cl.idcod_cliente 
          LEFT JOIN
            CREDKAR ck ON ck.CCODCTA = cm.CCODCTA
          WHERE 
            cm.Cestado = 'F' AND 
            cm.TipoEnti = 'INDI'
          GROUP BY 
            cm.CCODCTA, 
            cm.CodCli, 
            cl.short_name
        `);

        // Depuración: Inspeccionar los valores de cuotas
        console.log(
          "Datos devueltos por searchCredits:",
          rows.map((row) => ({
            nombre: row.nombre,
            ccodcta: row.ccodcta,
            monsug: row.monsug,
            Saldo: row.Saldo,
            estado: row.estado,
            eliminado: row.eliminado,
          }))
        );

        return rows;
      } catch (error) {
        console.error("Error executing searchCredits query:", error);
        throw new Error("Failed to fetch credits");
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    getClientDetails: async (_, { ccodcta }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(
          "SELECT * FROM cremcre_meta WHERE CCODCTA = ?",
          [ccodcta]
        );
        if (rows.length > 0) {
          return {
            ccodcta: rows[0].CCODCTA,
            nombre: rows[0].CodCli,
          };
        }
        throw new Error("Client not found");
      } catch (error) {
        console.error("Error fetching client details:", error);
        throw new Error("Failed to fetch client details");
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    getCreditDetails: async (
      _,
      { ccodcta },
      { primaryPool, secondaryPool }
    ) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(
          `
          SELECT 
            cl.short_name AS nombrecli,
            cl.idcod_cliente AS codcli,
            ag.cod_agenc AS codagencia,
            cm.CCODPRD AS codprod,
            cm.CCODCTA AS ccodcta,
            cm.MonSug AS monsug,
            cm.NIntApro AS interes,
            cm.DFecDsbls AS fecdesembolso,
            cm.noPeriodo AS cuotas,
            ce.Credito AS tipocred,
            per.nombre AS nomper,
            (
                cm.MonSug - (
                    SELECT IFNULL(SUM(ck.KP), 0)
                    FROM CREDKAR ck
                    WHERE ck.CESTADO != 'X'
                      AND ck.CTIPPAG = 'P'
                      AND ck.CCODCTA = cm.CCODCTA
                )
            ) AS saldocap,
            (
                (
                    SELECT IFNULL(SUM(nintere), 0)
                    FROM Cre_ppg
                    WHERE ccodcta = cm.CCODCTA
                ) - (
                    SELECT IFNULL(SUM(ck.INTERES), 0)
                    FROM CREDKAR ck
                    WHERE ck.CESTADO != 'X'
                      AND ck.CTIPPAG = 'P'
                      AND ck.CCODCTA = cm.CCODCTA
                )
            ) AS saldoint,
            prod.id_fondo,
            cl.url_img AS urlfoto
          FROM 
            cremcre_meta cm
            INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
            INNER JOIN tb_agencia ag ON cm.CODAgencia = ag.cod_agenc
            INNER JOIN cre_productos prod ON prod.id = cm.CCODPRD
            INNER JOIN jpxdcegu_bd_general_coopera.tb_credito ce ON cm.CtipCre = ce.abre
            INNER JOIN jpxdcegu_bd_general_coopera.tb_periodo per ON cm.NtipPerC = per.periodo
          WHERE 
            cm.Cestado = 'F' 
            AND cm.TipoEnti = 'INDI' 
            AND cm.CCODCTA = ?
          GROUP BY 
            cm.CCODCTA
        `,
          [ccodcta]
        );

        if (rows.length > 0) {
          const row = rows[0];
          return {
            nombrecli: handleEmptyValue(row.nombrecli),
            codcli: handleEmptyValue(row.codcli),
            codagencia: handleEmptyValue(row.codagencia),
            codprod: handleEmptyValue(row.codprod),
            ccodcta: handleEmptyValue(row.ccodcta),
            monsug: row.monsug,
            interes: row.interes,
            fecdesembolso: row.fecdesembolso
              ? row.fecdesembolso.toISOString().split("T")[0]
              : null,
            cuotas: row.cuotas,
            tipocred: handleEmptyValue(row.tipocred),
            nomper: handleEmptyValue(row.nomper),
            saldocap: row.saldocap,
            saldoint: row.saldoint,
            id_fondo: handleEmptyValue(row.id_fondo),
            urlfoto: handleEmptyValue(row.urlfoto),
          };
        }
        throw new Error("Credit details not found");
      } catch (error) {
        console.error("Error fetching credit details:", error);
        throw new Error("Failed to fetch credit details");
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },
    // esto es para las configuraciones de la tabla tb_configCre
    getConfigCre: async (_, __, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(`
          SELECT id, config_name, estado, comentario 
          FROM tb_configCre
        `);
        return rows.map((row) => ({
          id: row.id.toString(),
          config_name: row.config_name,
          estado: row.estado,
          comentario: row.comentario || null,
        }));
      } catch (error) {
        console.error("Error fetching tb_configCre:", error);
        throw new Error("Failed to fetch configurations: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    getBancos: async (_, __, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(`
      SELECT id, nombre, estado 
      FROM tb_bancos 
      WHERE estado = '1' 
      ORDER BY nombre
    `);

        return rows.map((row) => ({
          id: row.id,
          nombre: row.nombre,
          estado: row.estado,
        }));
      } catch (error) {
        console.error("Error fetching bancos:", error);
        throw new Error("Error al cargar bancos: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    getCuentasBanco: async (_, { bancoid }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();

        // Consulta
        const [rows] = await primaryConnection.query(
          `
      SELECT cbn.id, cbn.numcuenta, cbn.id_nomenclatura 
      FROM tb_bancos bn 
      INNER JOIN ctb_bancos cbn ON bn.id = cbn.id_banco 
      WHERE bn.estado = '1' AND cbn.estado = '1' AND bn.id = ?
      ORDER BY cbn.numcuenta
    `,
          [bancoid]
        );

        if (rows.length === 0) {
          throw new Error("El banco seleccionado no tiene cuentas creadas");
        }

        return rows.map((row) => ({
          id: row.id.toString(),
          numcuenta: row.numcuenta,
          id_nomenclatura: row.id_nomenclatura,
        }));
      } catch (error) {
        console.error("Error fetching cuentas banco:", error);
        throw new Error("Error al cargar cuentas del banco: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release(); primaryConnection.release();
      }
    },

    getPaymentPlan: async (_, { ccodcta }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();

        const hoy = new Date().toISOString().split("T")[0];

        const [rows] = await primaryConnection.query(
          `
      SELECT 
        cpg.Id_ppg AS id, 
        cpg.dfecven, 
        IF((TIMESTAMPDIFF(DAY,cpg.dfecven,?)) < 0, 0, (TIMESTAMPDIFF(DAY,cpg.dfecven,?))) AS diasatraso, 
        cpg.cestado, 
        cpg.cnrocuo AS numcuota, 
        cpg.ncappag AS capital, 
        cpg.nintpag AS interes, 
        cpg.nmorpag AS mora, 
        cpg.AhoPrgPag AS ahorropro, 
        cpg.OtrosPagosPag AS otrospagos
      FROM Cre_ppg cpg
      WHERE cpg.cestado='X' AND cpg.ccodcta=?
      ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo
    `,
          [hoy, hoy, ccodcta]
        );

        return rows.map((row) => ({
          id: row.id.toString(),
          dfecven: row.dfecven,
          diasatraso: row.diasatraso,
          cestado: row.cestado,
          numcuota: row.numcuota,
          capital: parseFloat(row.capital || 0),
          interes: parseFloat(row.interes || 0),
          mora: parseFloat(row.mora || 0),
          ahorropro: parseFloat(row.ahorropro || 0),
          otrospagos: parseFloat(row.otrospagos || 0),
        }));
      } catch (error) {
        console.error("Error fetching payment plan:", error);
        throw new Error("Failed to fetch payment plan");
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

      getUserClients: async (_, { userId }, { primaryPool }) => {
    let primaryConnection;
    try {
      primaryConnection = await primaryPool.getConnection();
      
      const [rows] = await primaryConnection.query(`
        SELECT 
          idcod_cliente,
          short_name,
          date_birth,
          fecha_alta
        FROM tb_cliente 
        WHERE created_by = ?
          AND fecha_alta != '0000-00-00 00:00:00'
          AND fecha_alta IS NOT NULL
        ORDER BY fecha_alta DESC

      `, [userId]);

      return rows.map(row => ({
        idcod_cliente: row.idcod_cliente,
        short_name: handleEmptyValue(row.short_name),
        date_birth: row.date_birth ? row.date_birth.toISOString().split('T')[0] : null,
        fecha_alta: row.fecha_alta ? row.fecha_alta.toISOString() : null,
      }));

    } catch (error) {
      console.error("Error fetching user clients:", error);
      throw new Error("Error al obtener clientes del usuario: " + error.message);
    } finally {
      if (primaryConnection) primaryConnection.release();
    }
    },

    // Buscar clientes con créditos activos
   searchClientesCreditos: async (_, { searchTerm }, { primaryPool }) => {
  let primaryConnection;
  try {
    primaryConnection = await primaryPool.getConnection();
    
    let query = `
      SELECT 
        cm.CCODCTA AS ccodcta,
        cm.CodCli AS codcli,
        cl.no_identifica AS dpi,
        cl.short_name AS nombre,
        cl.Direccion AS direccion,
        cl.tel_no1 AS telefono,
        cm.MonSug AS monsug,
        cm.Cestado AS estado,
        cm.DFecDsbls AS fecha_desembolso,
        cm.NIntApro AS tasa_interes,
        cm.noPeriodo AS num_cuotas,
        cm.CtipCre AS tipo_credito
      FROM 
        cremcre_meta cm
        INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
      WHERE 
        cm.Cestado IN ('F', 'G')
    `;
        
        let params = [];
        
        // Si hay término de búsqueda, agregar filtros
        if (searchTerm && searchTerm.trim() !== "") {
          query += ` AND (
            cl.short_name LIKE ? OR 
            cl.no_identifica LIKE ? OR 
            cm.CCODCTA LIKE ?
          )`;
          const searchPattern = `%${searchTerm.trim()}%`;
          params = [searchPattern, searchPattern, searchPattern];
        }
        
        query += ` ORDER BY cm.CCODCTA ASC LIMIT 50`;
        
        const [rows] = await primaryConnection.query(query, params);
        
       return rows.map(row => ({
      ccodcta: row.ccodcta,
      codcli: row.codcli,
      dpi: handleEmptyValue(row.dpi),
      nombre: handleEmptyValue(row.nombre),
      direccion: handleEmptyValue(row.direccion),
      telefono: handleEmptyValue(row.telefono),
      monsug: parseFloat(row.monsug || 0),
      estado: row.estado === 'F' ? 'VIGENTE' : 'CANCELADO',
      fecha_desembolso: row.fecha_desembolso ? row.fecha_desembolso.toISOString().split('T')[0] : null,
      tasa_interes: parseFloat(row.tasa_interes || 0),
      num_cuotas: row.num_cuotas || 0,
      tipo_credito: handleEmptyValue(row.tipo_credito)
    }));
        
      } catch (error) {
        console.error("Error searching clientes creditos:", error);
        throw new Error("Error al buscar clientes con créditos: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    // Obtener estado de cuenta de un crédito específico
    getEstadoCuenta: async (_, { ccodcta }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        
        const [rows] = await primaryConnection.query(`
          SELECT 
            cred.DFECPRO as dfecpro,
            cred.CNROCUO as cnrocuo,
            cred.NMONTO as nmonto,
            cred.CNUMING as cnuming,
            cred.CCONCEP as cconcep,
            cred.KP as kp,
            cred.INTERES as interes,
            cred.MORA as mora,
            cred.AHOPRG as ahoprg,
            cred.OTR as otr,
            cred.CTIPPAG as ctippag
          FROM CREDKAR cred 
          WHERE cred.CESTADO != 'X' 
            AND cred.CTIPPAG = 'P' 
            AND cred.CCODCTA = ? 
          ORDER BY cred.DFECPRO, cred.CNROCUO
        `, [ccodcta]);
        
        return rows.map(row => ({
          dfecpro: row.dfecpro ? row.dfecpro.toISOString().split('T')[0] : null,
          cnrocuo: row.cnrocuo,
          nmonto: parseFloat(row.nmonto || 0),
          cnuming: handleEmptyValue(row.cnuming),
          cconcep: handleEmptyValue(row.cconcep),
          kp: parseFloat(row.kp || 0),
          interes: parseFloat(row.interes || 0),
          mora: parseFloat(row.mora || 0),
          ahoprg: parseFloat(row.ahoprg || 0),
          otr: parseFloat(row.otr || 0),
          ctippag: handleEmptyValue(row.ctippag)
        }));
        
      } catch (error) {
        console.error("Error getting estado cuenta:", error);
        throw new Error("Error al obtener estado de cuenta: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

// Obtener pagos procesados por el usuario
    getUserPayments: async (_, { userId }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        
        const [rows] = await primaryConnection.query(`
          SELECT 
            CCODCTA as ccodcta,
            DFECPRO as dfecpro,
            CCONCEP as cconcep,
            KP as kp,
            INTERES as interes,
            MORA as mora,
            AHOPRG as ahoprg,
            OTR as otr,
            boletabanco,
            DFECMOD as dfecmod
          FROM CREDKAR  
          WHERE CCODUSU = ?
            AND DFECMOD != '0000-00-00 00:00:00'
            AND DFECMOD IS NOT NULL
            AND CTIPPAG = 'P'
          ORDER BY DFECMOD DESC
          LIMIT 200
        `, [userId]);

        return rows.map(row => ({
          ccodcta: row.ccodcta,
          dfecpro: row.dfecpro ? row.dfecpro.toISOString() : null,
          cconcep: handleEmptyValue(row.cconcep),
          kp: parseFloat(row.kp || 0),
          interes: parseFloat(row.interes || 0),
          mora: parseFloat(row.mora || 0),
          ahoprg: parseFloat(row.ahoprg || 0),
          otr: parseFloat(row.otr || 0),
          boletabanco: handleEmptyValue(row.boletabanco),
          dfecmod: row.dfecmod ? row.dfecmod.toISOString() : null,
        }));

      } catch (error) {
        console.error("Error fetching user payments:", error);
        throw new Error("Error al obtener pagos del usuario: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },


    // SECCION DE MODULO DE SOLICITUD
    // ┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴┴
    //----------------------------------------------------------------------------------
    //----------------------------------------------------------------------------------

    searchClients: async (_, { searchTerm }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();

        let query = `
      SELECT 
        tc.idcod_cliente,
        tc.short_name,
        tc.no_identifica,
        tc.Direccion,
        tc.Direccion,
        tc.tel_no1,
        COALESCE(cm.NCiclo, 0) + 1 AS ciclo
      FROM 
        tb_cliente tc
      LEFT JOIN 
        (SELECT 
           CodCli, 
           MAX(NCiclo) AS NCiclo 
         FROM 
           cremcre_meta 
         GROUP BY 
           CodCli) cm 
        ON tc.idcod_cliente = cm.CodCli
      WHERE 
        tc.estado = 1
    `;

        let params = [];

        // Si hay término de búsqueda, agregar filtros
        if (searchTerm && searchTerm.trim() !== "") {
          query += ` AND (
        tc.short_name LIKE ? OR 
        tc.no_identifica LIKE ? OR 
        tc.idcod_cliente LIKE ?
      )`;
          const searchPattern = `%${searchTerm.trim()}%`;
          params = [searchPattern, searchPattern, searchPattern];
        }

       

        const [rows] = await primaryConnection.query(query, params);

        return rows.map((row) => ({
          idcod_cliente: row.idcod_cliente,
          short_name: handleEmptyValue(row.short_name),
          no_identifica: handleEmptyValue(row.no_identifica),
          direccion: handleEmptyValue(row.Direccion),
          tel_no1: handleEmptyValue(row.tel_no1),
          ciclo: row.ciclo || 1,
        }));
      } catch (error) {
        console.error("Error searching clients:", error);
        throw new Error("Error al buscar clientes: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    // busca linea de credito
    searchLines: async (_, { searchTerm }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();

        let query = `
      SELECT 
        pro.id,
        pro.cod_producto,
        pro.nombre AS nompro,
        pro.descripcion AS descriprod,
        ff.descripcion AS fondesc,
        pro.tasa_interes,
        pro.monto_maximo
      FROM 
        cre_productos pro
      INNER JOIN 
        ctb_fuente_fondos ff ON ff.id = pro.id_fondo 
      WHERE 
        pro.estado = 1
    `;

        let params = [];

        if (searchTerm && searchTerm.trim() !== "") {
          query += ` AND (
        pro.nombre LIKE ? OR 
        pro.cod_producto LIKE ? OR 
        pro.descripcion LIKE ?
      )`;
          const searchPattern = `%${searchTerm.trim()}%`;
          params = [searchPattern, searchPattern, searchPattern];
        }

        query += ` ORDER BY pro.id ASC LIMIT 50`;

        const [rows] = await primaryConnection.query(query, params);

        return rows.map((row) => ({
          id: row.id.toString(),
          codigo: row.cod_producto,
          nombre: row.nompro,
          descripcion: row.descriprod || "Sin descripción",
          fondos: row.fondesc || "N/A",
          tasa: row.tasa_interes.toString(),
          monto: row.monto_maximo.toString(),
        }));
      } catch (error) {
        console.error("Error searching lines:", error);
        throw new Error("Error al buscar líneas de crédito: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    // obtiene las agencias:
    getAgencias: async (_, __, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(`
      SELECT nom_agencia, id_agencia 
      FROM tb_agencia 
      ORDER BY nom_agencia
    `);

        return rows.map((row) => ({
          id: row.id_agencia.toString(),
          nombre: row.nom_agencia,
        }));
      } catch (error) {
        console.error("Error fetching agencias:", error);
        throw new Error("Error al cargar agencias: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    getAnalistas: async (_, __, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        const [rows] = await primaryConnection.query(`
      SELECT CONCAT(nombre, ' ', apellido) AS nameusu, id_usu 
      FROM tb_usuario 
      WHERE puesto = 'ANA' AND estado = 1
      ORDER BY nombre, apellido
    `);

        return rows.map((row) => ({
          id: row.id_usu.toString(),
          nombre: row.nameusu,
        }));
      } catch (error) {
        console.error("Error fetching analistas:", error);
        throw new Error("Error al cargar analistas: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

    getDestinosCredito: async (_, __, { secondaryPool }) => {
      let secondaryConnection;
      try {
        secondaryConnection = await secondaryPool.getConnection();
        const [rows] = await secondaryConnection.query(`
      SELECT id_DestinoCredito AS id, DestinoCredito AS destino 
      FROM tb_destinocredito
      ORDER BY destino
    `);

        return rows.map((row) => ({
          id: row.id.toString(),
          destino: row.destino,
        }));
      } catch (error) {
        console.error("Error fetching destinos credito:", error);
        throw new Error(
          "Error al cargar destinos de crédito: " + error.message
        );
      } finally {
        if (secondaryConnection) secondaryConnection.release();
      }
    },

    getSectoresEconomicos: async (_, __, { secondaryPool }) => {
      let secondaryConnection;
      try {
        secondaryConnection = await secondaryPool.getConnection();
        const [rows] = await secondaryConnection.query(`
      SELECT id_SectoresEconomicos, SectoresEconomicos 
      FROM tb_sectoreseconomicos
      ORDER BY SectoresEconomicos
    `);

        return rows.map((row) => ({
          id: row.id_SectoresEconomicos.toString(),
          nombre: row.SectoresEconomicos,
        }));
      } catch (error) {
        console.error("Error fetching sectores economicos:", error);
        throw new Error(
          "Error al cargar sectores económicos: " + error.message
        );
      } finally {
        if (secondaryConnection) secondaryConnection.release();
      }
    },

    getActividadesEconomicas: async (_, { sectorId }, { secondaryPool }) => {
      let secondaryConnection;
      try {
        secondaryConnection = await secondaryPool.getConnection();

        if (!sectorId) {
          return []; // Retornar array vacío
        }

        const [rows] = await secondaryConnection.query(
          `
      SELECT id_ActiEcono, Titulo 
      FROM tb_ActiEcono 
      WHERE Id_SctrEcono = ?
      ORDER BY Titulo
    `,
          [sectorId]
        );

        return rows.map((row) => ({
          id: row.id_ActiEcono.toString(),
          titulo: row.Titulo,
        }));
      } catch (error) {
        console.error("Error fetching actividades economicas:", error);
        throw new Error(
          "Error al cargar actividades económicas: " + error.message
        );
      } finally {
        if (secondaryConnection) secondaryConnection.release();
      }
    },

    // Obtiene los tipos de crédito
    getTiposCredito: async (_, __, { secondaryPool }) => {
      let secondaryConnection;
      try {
        secondaryConnection = await secondaryPool.getConnection();
        const [rows] = await secondaryConnection.query(`
      SELECT abre, Credito 
      FROM tb_credito
      ORDER BY Credito
    `);

        return rows.map((row) => ({
          abre: row.abre,
          credito: row.Credito,
        }));
      } catch (error) {
        console.error("Error fetching tipos credito:", error);
        throw new Error("Error al cargar tipos de crédito: " + error.message);
      } finally {
        if (secondaryConnection) secondaryConnection.release();
      }
    },

    getTiposPeriodo: async (_, { tipoCredito }, { secondaryPool }) => {
      let secondaryConnection;
      try {
        secondaryConnection = await secondaryPool.getConnection();

        if (!tipoCredito) {
          return []; // Retornar array vacío
        }

        // Determinar el filtro según el tipo de crédito
        let whereClause;
        if (tipoCredito === "Flat") {
          whereClause = "id BETWEEN 1 AND 9";
        } else if (tipoCredito === "Franc" || tipoCredito === "Germa") {
          whereClause = "id BETWEEN 5 AND 9";
        } else if (tipoCredito === "Amer") {
          whereClause = "id BETWEEN 4 AND 9";
        } else {
          throw new Error("Tipo de crédito no válido");
        }

        const [rows] = await secondaryConnection.query(`
      SELECT id, descripcion, dias, cod_msplus
      FROM tb_cre_periodos
      WHERE ${whereClause.replace(/[^a-zA-Z0-9\s=><BETWEEN AND]/g, '')}
      ORDER BY descripcion
    `);

        return rows.map((row) => ({
          id: row.id.toString(),
          descripcion: row.descripcion,
          dias: row.dias,
          cod_msplus: row.cod_msplus,
        }));
      } catch (error) {
        console.error("Error fetching tipos periodo:", error);
        throw new Error("Error al cargar tipos de período: " + error.message);
      } finally {
        if (secondaryConnection) secondaryConnection.release();
      }
    },

    getClienteGarantias: async (
      _,
      { clienteId },
      { primaryPool, secondaryPool }
    ) => {
      let primaryConnection;
      let secondaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        secondaryConnection = await secondaryPool.getConnection();

        // Primero obtenemos los datos del cliente
        const [clienteRows] = await primaryConnection.query(
          `
      SELECT 
        tc.idcod_cliente,
        tc.short_name,
        tc.no_identifica,
        tc.Direccion,
        tc.tel_no1,
        COALESCE(cm.NCiclo, 0) + 1 AS ciclo
      FROM 
        tb_cliente tc
      LEFT JOIN 
        (SELECT 
           CodCli, 
           MAX(NCiclo) AS NCiclo 
         FROM 
           cremcre_meta 
         GROUP BY 
           CodCli) cm 
        ON tc.idcod_cliente = cm.CodCli
      WHERE 
        tc.estado = 1 AND tc.idcod_cliente = ?
    `,
          [clienteId]
        );

        if (clienteRows.length === 0) {
          throw new Error("Cliente no encontrado");
        }

        const cliente = {
          idcod_cliente: clienteRows[0].idcod_cliente,
          short_name: handleEmptyValue(clienteRows[0].short_name),
          no_identifica: handleEmptyValue(clienteRows[0].no_identifica),
          direccion: handleEmptyValue(clienteRows[0].Direccion),
          tel_no1: handleEmptyValue(clienteRows[0].tel_no1),
          ciclo: clienteRows[0].ciclo || 1,
        };

        // Ahora obtenemos las garantías del cliente usando ambos pools
        const [garantiasRows] = await primaryConnection.query(
          `
      SELECT 
        cl.idcod_cliente AS codcli, 
        gr.idGarantia AS idgar, 
        tipgar.id_TiposGarantia AS idtipgar, 
        tipgar.TiposGarantia AS nomtipgar, 
        tipc.idDoc AS idtipdoc, 
        tipc.NombreDoc AS nomtipdoc,
        gr.descripcionGarantia AS descripcion, 
        gr.direccion AS direccion, 
        gr.montoGravamen AS montogravamen,
        IFNULL((SELECT cl2.short_name AS nomcli 
                FROM tb_cliente cl2 
                WHERE cl2.idcod_cliente=gr.descripcionGarantia 
                AND tipgar.id_TiposGarantia=1 
                AND tipc.idDoc=1),'x') AS nomcli,
        IFNULL((SELECT cl2.Direccion AS direccioncli 
                FROM tb_cliente cl2 
                WHERE cl2.idcod_cliente=gr.descripcionGarantia 
                AND tipgar.id_TiposGarantia=1 
                AND tipc.idDoc=1),'x') AS direccioncli
      FROM 
        tb_cliente cl
      INNER JOIN 
        cli_garantia gr ON cl.idcod_cliente=gr.idCliente
      INNER JOIN 
        ${process.env.DDBB_NAME_GENERAL}.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
      INNER JOIN 
        ${process.env.DDBB_NAME_GENERAL}.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
      WHERE 
        cl.estado='1' 
        AND gr.estado=1 
        AND cl.idcod_cliente=?
    `,
          [clienteId]
        );

        const garantias = garantiasRows.map((row) => ({
          codcli: row.codcli,
          idgar: row.idgar.toString(),
          idtipgar: row.idtipgar.toString(),
          nomtipgar: handleEmptyValue(row.nomtipgar),
          idtipdoc: row.idtipdoc.toString(),
          nomtipdoc: handleEmptyValue(row.nomtipdoc),
          descripcion: handleEmptyValue(row.descripcion),
          direccion: handleEmptyValue(row.direccion),
          montogravamen: parseFloat(row.montogravamen || 0),
          nomcli: row.nomcli !== "x" ? row.nomcli : null,
          direccioncli: row.direccioncli !== "x" ? row.direccioncli : null,
        }));

        return {
          cliente,
          garantias,
          tieneGarantias: garantias.length > 0,
        };
      } catch (error) {
        console.error("Error obteniendo garantías del cliente:", error);
        throw new Error(
          "Error al obtener garantías del cliente: " + error.message
        );
      } finally {
        if (primaryConnection) primaryConnection.release();
        if (secondaryConnection) secondaryConnection.release();
      }
    },

     getDocumentFormat: async (_, { reportId }, { dataSources }) => {
      try {
        const query = `
          SELECT id, id_reporte, nombre, file 
          FROM tb_documentos 
          WHERE id_reporte = ? 
          LIMIT 1
        `;
        
        const result = await dataSources.database.query(query, [reportId]);
        
        if (result && result.length > 0) {
          return {
            id: result[0].id,
            id_reporte: result[0].id_reporte,
            nombre: result[0].nombre,
            file: result[0].file || 'html'
          };
        }
        
        return null;
      } catch (error) {
        console.error('Error obteniendo formato de documento:', error);
        throw new Error('Error al obtener configuración de formato');
      }
    },
  

    searchClientesHistorial: async (_, { searchTerm }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        
        let query = `
          SELECT 
            cm.CCODCTA AS ccodcta, 
            cm.CodCli AS codcli, 
            cl.short_name AS nombre, 
            cm.NCiclo AS ciclo, 
            cm.MonSug AS monsug, 
            cm.TipoEnti AS tipocred, 
            cm.Cestado AS estado
          FROM cremcre_meta cm
          INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
          WHERE (cm.Cestado='F') AND cm.TipoEnti = 'INDI'
        `;
        
        let params = [];
        
        if (searchTerm && searchTerm.trim() !== "") {
          query += ` AND (
            cl.short_name LIKE ? OR 
            cl.no_identifica LIKE ? OR 
            cm.CCODCTA LIKE ?
          )`;
          const searchPattern = `%${searchTerm.trim()}%`;
          params = [searchPattern, searchPattern, searchPattern];
        }
        
        query += ` ORDER BY cm.CCODCTA ASC LIMIT 50`;
        
        const [rows] = await primaryConnection.query(query, params);
        
        return rows.map(row => ({
          ccodcta: row.ccodcta,
          codcli: row.codcli,
          nombre: handleEmptyValue(row.nombre),
          ciclo: row.ciclo || 0,
          monsug: parseFloat(row.monsug || 0),
          tipocred: row.tipocred || 'INDI',
          estado: row.estado || 'F'
        }));
        
      } catch (error) {
        console.error("Error searching clientes historial:", error);
        throw new Error("Error al buscar clientes para historial: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },

   getPlanPagos: async (_, { ccodcta }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();
        
        const hoy = new Date().toISOString().split('T')[0];
        
        const [rows] = await primaryConnection.query(`
          SELECT 
            cnrocuo,
            dfecven,
            dfecpag,
            IF((TIMESTAMPDIFF(DAY,dfecven,?)) < 0, 0, (TIMESTAMPDIFF(DAY,dfecven,?))) AS diasatraso,
            cestado,
            ncapita,
            nintere,
            cflag
          FROM Cre_ppg 
          WHERE ccodcta = ?
          ORDER BY cnrocuo ASC
        `, [hoy, hoy, ccodcta]);
        
        return rows.map(row => ({
          cnrocuo: row.cnrocuo,
          dfecven: row.dfecven && !isNaN(new Date(row.dfecven)) ? 
            new Date(row.dfecven).toISOString().split('T')[0] : null,
          dfecpag: row.dfecpag && !isNaN(new Date(row.dfecpag)) ? 
            new Date(row.dfecpag).toISOString().split('T')[0] : null,
          diasatraso: parseInt(row.diasatraso) || 0,
          cestado: handleEmptyValue(row.cestado),
          ncapita: parseFloat(row.ncapita || 0),
          nintere: parseFloat(row.nintere || 0),
          cflag: handleEmptyValue(row.cflag)
        }));
        
      } catch (error) {
        console.error("Error getting plan pagos:", error);
        throw new Error("Error al obtener plan de pagos: " + error.message);
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },









    //---------------END SECCION DE MODULO DE SOLICITUD---------------------------------
    //----------------------------------------------------------------------------------
  },

  //----------------------------------------------------------------------------------
  //----------------------------------------------------------------------------------
  //---------------PARTE DE MUTATION--------------------------------------------------
  //----------------------------------------------------------------------------------
  //mutaciones
  Mutation: {
    login: async (_, { usu, pass }, { primaryPool }) => {
      try {
        console.log("Intentando login para usuario:", usu);
        const [rows] = await primaryPool.query(
          "SELECT tbu.id_usu, tbu.usu, tbu.pass, tbu.nombre, tbu.apellido, tbu.dpi, tbu.estado, tbu.puesto, " +
            "tbg.id_agencia, tbg.cod_agenc, tbg.nom_agencia " +
            "FROM tb_usuario tbu " +
            "INNER JOIN tb_agencia tbg ON tbu.id_agencia = tbg.id_agencia " +
            "WHERE tbu.usu = ?",
          [usu]
        );

        console.log("Resultado de la consulta:", rows);
        if (!rows || rows.length === 0) {
          throw new Error("Usuario no encontrado");
        }

        const user = rows[0];
        const encryptedPass = user.pass;

        try {
          console.log("Desencriptando contraseña:", encryptedPass);
          const decryptedPass = await desencriptarConPHP(encryptedPass);
          
          if (decryptedPass !== pass) {
            throw new Error("Contraseña incorrecta");
          }

          const token = jwt.sign(
            { id: user.id, usu: user.usu },
            process.env.JWT_SECRET,
            { expiresIn: '8h' }
          );

          return {
            token,
            user: {
              id: handleEmptyValue(user.id_usu),
              usu: handleEmptyValue(user.usu),
            },
            nombre: handleEmptyValue(user.nombre),
            apellido: handleEmptyValue(user.apellido),
            dpi: handleEmptyValue(user.dpi),
            estado: handleEmptyValue(user.estado),
            id_agencia: handleEmptyValue(user.id_agencia),
            cod_agenc: handleEmptyValue(user.cod_agenc),
            nom_agencia: handleEmptyValue(user.nom_agencia),
          };
        } catch (decryptError) {
          console.error("Error al desencriptar:", decryptError.message);
          throw new Error(
            "Error al verificar credenciales: " + decryptError.message
          );
        }
      } catch (error) {
        console.error("Error en login:", error.message);
        throw new Error(error.message || "Error desconocido en login");
      }
    },

    //  sección Mutation en tu resolvers.js

    savePayment: async (_, { paymentData }, { primaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();

        // Iniciar transacción
        await primaryConnection.beginTransaction();

        // 1. Obtener el siguiente número de recibo si es automático
        let finalReceiptNumber = paymentData.numrecibo;
        if (paymentData.numrecibo === "automatico") {
          const [receiptRows] = await primaryConnection.query(`
    SELECT MAX(CAST(CNUMING AS UNSIGNED)) + 1 AS cnumming
    FROM CREDKAR
    WHERE CNUMING REGEXP '^[0-9]+$'
  `);

          const nextNumber =
            receiptRows[0] && receiptRows[0].cnumming
              ? receiptRows[0].cnumming
              : 1;
          finalReceiptNumber = nextNumber.toString();

          console.log(
            "Número de recibo generado automáticamente:",
            finalReceiptNumber
          );
        }

        // 2. Validar que el capital no supere el saldo pendiente
        const [saldoRows] = await primaryConnection.query(
          `
      SELECT 
        IFNULL((ROUND((IFNULL(cm.NCapDes,0)),2)-(SELECT ROUND(IFNULL(SUM(c.KP),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X')),0) AS saldopendiente,
        IFNULL(ROUND((SELECT ROUND(IFNULL(SUM(nintere),0),2) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA)-(SELECT ROUND(IFNULL(SUM(c.INTERES),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X'),2),0) AS intpendiente 
      FROM cremcre_meta cm 
      WHERE cm.CCODCTA = ?
    `,
          [paymentData.ccodcta]
        );

        const capitalPendiente =
          saldoRows[0] && saldoRows[0].saldopendiente
            ? saldoRows[0].saldopendiente
            : 0;
        const interesPendiente =
          saldoRows[0] && saldoRows[0].intpendiente
            ? saldoRows[0].intpendiente
            : 0;

        if (paymentData.capital > capitalPendiente) {
          throw new Error(
            `No puede completar el pago, porque el saldo capital por pagar es de ${capitalPendiente} y usted quiere hacer un pago de ${paymentData.capital}`
          );
        }

        if (paymentData.interes > interesPendiente) {
          throw new Error(
            `No puede completar el pago, porque el saldo interés por pagar es de ${interesPendiente} y usted quiere hacer un pago de ${paymentData.interes}`
          );
        }

        // 3. Validar boleta de banco si es necesario
        if (paymentData.metodoPago === "2") {
          if (
            !paymentData.boletabanco ||
            !paymentData.bancoid ||
            !paymentData.cuentaid
          ) {
            throw new Error(
              "Debe completar todos los datos de la boleta de banco"
            );
          }

          // Verificar que la boleta no exista
          const [boletaCheck] = await primaryConnection.query(
            `
        SELECT EXISTS (
          SELECT boletabanco 
          FROM CREDKAR 
          WHERE CTIPPAG='P' AND boletabanco=? AND CBANCO=?
        ) AS result
      `,
            [paymentData.boletabanco, paymentData.bancoid]
          );

          const boletaExists =
            boletaCheck[0] && boletaCheck[0].result ? boletaCheck[0].result : 0;
          if (boletaExists) {
            throw new Error(
              `El Número de boleta ${paymentData.boletabanco} ya se ingresó en el sistema`
            );
          }
        }

        // 4. Obtener configuraciones necesarias
        const [agenciaRows] = await primaryConnection.query(
          `
      SELECT id_nomenclatura_caja 
      FROM tb_agencia 
      WHERE id_agencia = ?
    `,
          [paymentData.agencyId]
        );

        if (!agenciaRows.length) {
          throw new Error("No se encontró la cuenta contable para el pago");
        }

        let idNomenclaturaCaja = agenciaRows[0].id_nomenclatura_caja;

        // 5. Obtener cuentas contables del producto
        const [prodRows] = await primaryConnection.query(
          `
      SELECT cp.id_cuenta_capital, cp.id_cuenta_interes, cp.id_cuenta_mora, cp.id_cuenta_otros 
      FROM cre_productos cp 
      INNER JOIN cremcre_meta cm ON cp.id = cm.CCODPRD 
      WHERE cm.CCODCTA = ?
    `,
          [paymentData.ccodcta]
        );

        if (!prodRows.length) {
          throw new Error(
            "No se encontraron las cuentas contables para el producto"
          );
        }

        const cuentas = prodRows[0];

        // 6. Ajustar nomenclatura si es boleta de banco
        if (paymentData.metodoPago === "2") {
          const [bancoRows] = await primaryConnection.query(
            `
        SELECT id_nomenclatura 
        FROM ctb_bancos 
        WHERE id = ?
      `,
            [paymentData.cuentaid]
          );

          if (bancoRows.length) {
            idNomenclaturaCaja = bancoRows[0].id_nomenclatura;
          }
        }

        // 7. Obtener número de cuota
        const [cuotaRows] = await primaryConnection.query(
          `
      SELECT IFNULL(MAX(CNROCUO), 0) + 1 as siguiente_cuota
      FROM CREDKAR 
      WHERE CCODCTA = ?
    `,
          [paymentData.ccodcta]
        );

        const numeroCuota =
          cuotaRows[0] && cuotaRows[0].siguiente_cuota
            ? cuotaRows[0].siguiente_cuota
            : 1;

        // 8. Preparar datos para inserción
        const hoy = new Date().toISOString().slice(0, 19).replace("T", " ");
        const fechaPago = paymentData.fecpag;
        const fechaBanco =
          paymentData.metodoPago === "2" ? paymentData.fecpagBANC : fechaPago;
        const desboleta =
          paymentData.metodoPago === "2"
            ? ` - BOLETA DE BANCO NO. ${paymentData.boletabanco}`
            : "";
        // const concepto = `- PAGO DE CRÉDITO EN POS A NOMBRE DE ${paymentData.userName.toUpperCase()} CON NÚMERO DE RECIBO ${finalReceiptNumber}${desboleta}`;
        const concepto = `- PAGO DE CRÉDITO EN APLICACION POS CON NÚMERO DE RECIBO ${finalReceiptNumber}${desboleta}`;

        // 9. Insertar en CREDKAR
        await primaryConnection.query(
          `
      INSERT INTO CREDKAR (
        CCODCTA, DFECPRO, DFECSIS, CNROCUO, NMONTO, CNUMING, CCONCEP, 
        KP, INTERES, MORA, OTR, CCODOFI, CCODUSU, CTIPPAG, CMONEDA, 
        DFECMOD, CESTADO, CBANCO, CCODBANCO, DFECBANCO, FormPago, boletabanco
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'P', 'Q', ?, '1', ?, ?, ?, ?, ?)
    `,
          [
            paymentData.ccodcta,
            fechaPago,
            hoy,
            numeroCuota,
            paymentData.total,
            finalReceiptNumber,
            concepto,
            paymentData.capital,
            paymentData.interes,
            paymentData.mora,
            paymentData.otros,
            paymentData.agencyId,
            paymentData.userId,
            hoy,
            paymentData.bancoid || "",
            paymentData.cuentaid || "",
            fechaBanco,
            paymentData.metodoPago,
            paymentData.boletabanco || "",
          ]
        );

        // 10. Actualizar plan de pagos
        await primaryConnection.query(`CALL update_ppg_account(?)`, [
          paymentData.ccodcta,
        ]);

        // 11. Calcular mora
        await primaryConnection.query(`SELECT calculo_mora(?)`, [
          paymentData.ccodcta,
        ]);

        // 12. Obtener número de partida contable
        const [partidaRows] = await primaryConnection.query(
          `
      SELECT IFNULL(MAX(numcom), 0) + 1 as siguiente_partida
      FROM ctb_diario 
      WHERE id_tb_usu = ?
    `,
          [paymentData.userId]
        );

        const numPartida =
          partidaRows[0] && partidaRows[0].siguiente_partida
            ? partidaRows[0].siguiente_partida
            : 1;

        // 13. Insertar en libro diario
        const [diarioResult] = await primaryConnection.query(
          `
      INSERT INTO ctb_diario (
        numcom, id_ctb_tipopoliza, id_tb_moneda, numdoc, glosa, 
        fecdoc, feccnt, cod_aux, id_tb_usu, fecmod, estado
      ) VALUES (?, 1, 1, ?, ?, ?, ?, ?, ?, ?, 1)
    `,
          [
            numPartida,
            finalReceiptNumber,
            concepto,
            fechaPago,
            fechaPago,
            paymentData.ccodcta,
            paymentData.userId,
            hoy,
          ]
        );

        const idCtbDiario = diarioResult.insertId;

        // 14. Obtener id_fondo del crédito
        const [fondoRows] = await primaryConnection.query(
          `
      SELECT prod.id_fondo 
      FROM cre_productos prod 
      INNER JOIN cremcre_meta cm ON prod.id = cm.CCODPRD 
      WHERE cm.CCODCTA = ?
    `,
          [paymentData.ccodcta]
        );

        const idFondo =
          fondoRows[0] && fondoRows[0].id_fondo ? fondoRows[0].id_fondo : 1;

        // 15. Insertar movimientos contables
        // Movimiento total (DEBE)
        await primaryConnection.query(
          `
      INSERT INTO ctb_mov (id_ctb_diario, id_fuente_fondo, id_ctb_nomenclatura, debe, haber) 
      VALUES (?, ?, ?, ?, 0)
    `,
          [idCtbDiario, idFondo, idNomenclaturaCaja, paymentData.total]
        );

        // Movimiento capital (HABER)
        if (paymentData.capital > 0) {
          await primaryConnection.query(
            `
        INSERT INTO ctb_mov (id_ctb_diario, id_fuente_fondo, id_ctb_nomenclatura, debe, haber) 
        VALUES (?, ?, ?, 0, ?)
      `,
            [
              idCtbDiario,
              idFondo,
              cuentas.id_cuenta_capital,
              paymentData.capital,
            ]
          );
        }

        // Movimiento interés (HABER)
        if (paymentData.interes > 0) {
          await primaryConnection.query(
            `
        INSERT INTO ctb_mov (id_ctb_diario, id_fuente_fondo, id_ctb_nomenclatura, debe, haber) 
        VALUES (?, ?, ?, 0, ?)
      `,
            [
              idCtbDiario,
              idFondo,
              cuentas.id_cuenta_interes,
              paymentData.interes,
            ]
          );
        }

        // Movimiento mora (HABER)
        if (paymentData.mora > 0) {
          await primaryConnection.query(
            `
        INSERT INTO ctb_mov (id_ctb_diario, id_fuente_fondo, id_ctb_nomenclatura, debe, haber) 
        VALUES (?, ?, ?, 0, ?)
      `,
            [idCtbDiario, idFondo, cuentas.id_cuenta_mora, paymentData.mora]
          );
        }

        // Movimiento otros (HABER)
        if (paymentData.otros > 0) {
          await primaryConnection.query(
            `
        INSERT INTO ctb_mov (id_ctb_diario, id_fuente_fondo, id_ctb_nomenclatura, debe, haber) 
        VALUES (?, ?, ?, 0, ?)
      `,
            [idCtbDiario, idFondo, cuentas.id_cuenta_otros, paymentData.otros]
          );
        }

        // 16. Confirmar transacción
        await primaryConnection.commit();

        return {
          success: true,
          message: `Pago registrado correctamente con recibo No. ${finalReceiptNumber}`,
          receiptNumber: finalReceiptNumber,
          cuotaNumber: numeroCuota,
        };
      } catch (error) {
        // Rollback en caso de error
        if (primaryConnection) {
          await primaryConnection.rollback();
        }
        console.error("Error en savePayment:", error);
        throw new Error(error.message || "Error al procesar el pago");
      } finally {
        if (primaryConnection) {
          primaryConnection.release();
        }
      }
    },
    //----------------------------------------------------------------------------------
    //----------------------------------------------------------------------------------
    //---------------END SECCION DE MODULO DE PAGO DE CREDITO --------------------------
    //----------------------------------------------------------------------------------



    //----------------------------------------------------------------------------------
    //------------- SECCION DE MODULO DE CREACION DE CLIENTES -------------------------
    //----------------------------------------------------------------------------------

crearCliente: async (_, { input }, { primaryPool }) => {
  let primaryConnection;
  try {
    primaryConnection = await primaryPool.getConnection();
    await primaryConnection.beginTransaction();

    // Validación de tipos de datos
    const validaciones = [
      { campo: 'edad', valor: input.edad, tipo: 'number' },
      { campo: 'numeroHijos', valor: input.numeroHijos, tipo: 'number' },
      { campo: 'dependencia', valor: input.dependencia, tipo: 'number' },
      { campo: 'email', valor: input.email, validacion: (email) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) },
      { campo: 'numeroDocumento', valor: input.numeroDocumento, validacion: (doc) => 
        input.tipoDocumento !== 'DPI' || /^\d{13}$/.test(doc)
      }
    ];

    for (const validacion of validaciones) {
      // if (validacion.tipo && typeof validacion.valor !== validacion.tipo) {
      //   throw new Error(`El campo ${validacion.campo} debe ser de tipo ${validacion.tipo}`);
      // }
      // if (validacion.validacion && !validacion.validacion(validacion.valor)) {
      //   throw new Error(`El campo ${validacion.campo} no tiene un formato válido`);
      // }
    }

    console.log('🔍 Datos recibidos en crearCliente:', JSON.stringify(input, null, 2));

    const {
      // Nombres
      primerNombre,
      segundoNombre,
      tercerNombre,
      // Apellidos  
      primerApellido,
      segundoApellido,
      tercerApellido,
      // Datos personales
      genero,
      estadoCivil,
      profesion,
      email,
      conyugue,
      telConyugue,
      // Nacimiento
      fechaNacimiento,
      edad,
      origen,
      paisNacimiento,
      departamentoNacimiento,
      municipioNacimiento,
      direccionNacimiento,
      // Documento
      documentoExtendido,
      tipoDocumento,
      numeroDocumento,
      tipoIdentTributaria,
      numeroNit,
      afiliacionIggs,
      nacionalidad,
      // Domicilio
      condicionVivienda,
      resideDesde,
      departamentoDomicilio,
      municipioDomicilio,
      direccionVivienda,
      referenciaVivienda,
      zona,
      barrio,
      telefono1,
      telefono2,
      actuaNombrePropio,
      representante,
      calidadActua,
      // Adicional
      otraNacionalidad,
      etnia,
      religion,
      educacion,
      relacionInstitucional,
      // Referencias
      ref1Nombre,
      ref1Telefono,
      ref1Parentesco,
      ref1Direccion,
      ref1Referencia,
      ref2Nombre,
      ref2Telefono,
      ref2Parentesco,
      ref2Direccion,
      ref2Referencia,
      ref3Nombre,
      ref3Telefono,
      ref3Parentesco,
      ref3Direccion,
      ref3Referencia,
      // Preguntas Si/No
      sabeLeer,
      sabeEscribir,
      firma,
      esPep,
      esCpe,
      // Datos adicionales
      numeroHijos,
      dependencia,
      codigoInterno,
      observaciones,
      // Usuario y Ubicación
      userId,
      agencyId,
      latitud,
      longitud
    } = input;

    // ✅ VALIDACIONES BÁSICAS
    if (!primerNombre || !primerApellido || !numeroDocumento) {
      throw new Error("Los campos primer nombre, primer apellido y número de documento son obligatorios");
    }

    // ✅ VERIFICAR DOCUMENTO NO EXISTA
    const [docCheck] = await primaryConnection.query(
      "SELECT idcod_cliente FROM tb_cliente WHERE no_identifica = ? AND estado != '0'",
      [numeroDocumento]
    );

    if (docCheck.length > 0) {
      throw new Error(`El número de documento ${numeroDocumento} ya está registrado en el sistema`);
    }

    // ✅ VERIFICAR EMAIL NO EXISTA (si se proporcionó un email)
    // if (email && email.trim() !== '') {
    //   const [emailCheck] = await primaryConnection.query(
    //     "SELECT idcod_cliente FROM tb_cliente WHERE email = ? AND email != '' AND estado = '1'",
    //     [email.trim().toLowerCase()]
    //   );

    //   if (emailCheck.length > 0) {
    //     throw new Error(`El email ${email} ya está registrado en el sistema`);
    //   }
    // }

    // if (emailCheck.length > 0) {
    //   throw new Error(`El email ${email} ya está registrado en el sistema`);
    // }

    // ✅ GENERAR NOMBRES AUTOMÁTICAMENTE
    const nombreCorto = `${primerNombre.trim()} ${primerApellido.trim()}`;
    const nombreCompleto = [
      primerNombre,
      segundoNombre,
      tercerNombre,
      primerApellido,
      segundoApellido,
      tercerApellido
    ].filter(n => n && n.trim()).join(' ').trim();

    // ✅ OBTENER CÓDIGO DE AGENCIA
    const [agenciaRows] = await primaryConnection.query(
      "SELECT cod_agenc FROM tb_agencia WHERE id_agencia = ?",
      [agencyId]
    );

    if (agenciaRows.length === 0) {
      throw new Error(`No se encontró la agencia especificada: ${agencyId}`);
    }

    const codigoAgencia = agenciaRows[0].cod_agenc;

    // ✅ GENERAR CÓDIGO DE CLIENTE
    const [maxIdResult] = await primaryConnection.query(
      `SELECT IFNULL(MAX(CAST(idcod_cliente AS UNSIGNED)), 0) + 1 AS nuevo_idcod_cliente
       FROM tb_cliente`
    );

    const codigoCliente = maxIdResult[0].nuevo_idcod_cliente.toString();

    if (!codigoCliente || codigoCliente === '0') {
      throw new Error("No se pudo generar el código de cliente");
    }

    console.log('✅ Código cliente generado:', codigoCliente);

    // ✅ PREPARAR DATOS PARA INSERCIÓN EN TB_CLIENTE
    const fechaActual = new Date().toISOString().slice(0, 19).replace("T", " ");
    const clienteData = [
      codigoCliente, "Natural", codigoAgencia, primerNombre, segundoNombre || "",
      tercerNombre || "", primerApellido, segundoApellido || "", tercerApellido || "",
      nombreCorto, nombreCompleto, "url", fechaNacimiento, genero, estadoCivil,
      origen, paisNacimiento || "Guatemala", departamentoNacimiento || "", municipioNacimiento || "",
      direccionNacimiento || "", tipoDocumento === "DPI" ? "3" : "1", numeroDocumento,
      documentoExtendido || "Guatemala", nacionalidad || "GT", departamentoNacimiento || "14",
      municipioNacimiento || "", otraNacionalidad || "", tipoIdentTributaria === "NIT" ? "1" : "2",
      numeroNit, afiliacionIggs || "", profesion, direccionVivienda, departamentoDomicilio,
      municipioDomicilio, referenciaVivienda || "", telefono1, telefono2, zona,
      parseInt(resideDesde), condicionVivienda, email, relacionInstitucional, 0.00,
      actuaNombrePropio === "1" ? "Si" : "No", representante || "", calidadActua || "",
      religion || "1", sabeLeer === "Si" ? "Si" : "No", sabeEscribir === "Si" ? "Si" : "No",
      firma === "Si" ? "Si" : "No", "", educacion || "primaria", etnia || "1",
      relacionInstitucional, observaciones || "", conyugue || "", telConyugue || "",
      zona, barrio, parseInt(numeroHijos) || 0, parseInt(dependencia) || 0,
      ref1Nombre || "", ref2Nombre || "", ref3Nombre || "", ref1Telefono || "",
      ref2Telefono || "", ref3Telefono || "", esPep === "Si" ? "Si" : "No",
      esCpe === "Si" ? "Si" : "No", codigoInterno || "", "1", userId, "",
      fechaActual, "", "", "", "",
      ref1Parentesco ? parseInt(ref1Parentesco) : 0,
      ref2Parentesco ? parseInt(ref2Parentesco) : 0,
    ];

    // ✅ INSERTAR EN TB_CLIENTE (80 CAMPOS)
    const insertQuery = `
      INSERT INTO tb_cliente (
        idcod_cliente, id_tipoCliente, agencia, primer_name, segundo_name, tercer_name,
        primer_last, segundo_last, casada_last, short_name, compl_name, url_img,
        date_birth, genero, estado_civil, origen, pais_nacio, depa_nacio, muni_nacio, aldea,
        type_doc, no_identifica, pais_extiende, nacionalidad, depa_extiende, muni_extiende, otra_nacion,
        identi_tribu, no_tributaria, no_igss, profesion, Direccion, depa_reside, muni_reside, aldea_reside,
        tel_no1, tel_no2, area, ano_reside, vivienda_Condi, email, relac_propo, monto_ingre, actu_Propio,
        representante_name, repre_calidad, id_religion, leer, escribir, firma, cargo_grupo,
        educa, idioma, Rel_insti, datos_Adicionales, Conyuge, telconyuge, zona, barrio,
        hijos, dependencia, Nomb_Ref1, Nomb_Ref2, Nomb_Ref3, Tel_Ref1, Tel_Ref2, Tel_Ref3,
        PEP, CPE, control_interno, estado, created_by, updated_by, fecha_alta, fecha_baja, fecha_mod, deleted_by,
        id_tb_cli_balance, parentesco1, parentesco2
      ) VALUES (${Array(80).fill('?').join(', ')})
    `;

    if (clienteData.length !== 80) {
      throw new Error(`Error de consistencia: Se esperan 80 valores para tb_cliente pero se proporcionaron ${clienteData.length}`);
    }
    await primaryConnection.query(insertQuery, clienteData);
    console.log('✅ Cliente insertado en tb_cliente con código:', codigoCliente);

    // ✅ INSERTAR UBICACIÓN EN CLI_ADICIONALES (SI EXISTE)
    if (latitud != null && longitud != null) {
      console.log(`📍 Guardando ubicación para cliente ${codigoCliente}: Lat ${latitud}, Lon ${longitud}`);
      
      const insertAdicionalQuery = `
        INSERT INTO cli_adicionales (
          entidad_tipo,
          entidad_id,
          descripcion,
          latitud,
          longitud,
          direccion_texto,
          estado,
          created_by,
          created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
      `;
      
      await primaryConnection.query(insertAdicionalQuery, [
        'cliente',
        codigoCliente,
        'Ubicación capturada durante registro',
        latitud,
        longitud,
        direccionVivienda,
        1, // estado activo
        userId || null
      ]);
      console.log('✅ Datos de ubicación guardados en cli_adicionales.');
    }

    // ✅ COMMIT TRANSACCIÓN
    await primaryConnection.commit();

    const response = {
      success: true,
      message: "Cliente creado exitosamente",
      idcod_cliente: codigoCliente,
    };

    console.log('📤 Respuesta del resolver:', response);
    return response;

  } catch (error) {
    if (primaryConnection) {
      await primaryConnection.rollback();
    }
    console.error('❌ Error creando cliente:', error);
    return {
      success: false,
      message: error.message || "Error al crear el cliente",
      idcod_cliente: null,
    };
  } finally {
    if (primaryConnection) primaryConnection.release();
  }
},

    //----------------------------------------------------------------------------------
    //----------------------------------------------------------------------------------
    //------------- SECCION DE MODULO DE PAGO DE SOLICITUD DE CREDITO ------------------
    //----------------------------------------------------------------------------------

    crearSolicitudCredito: async (_,{ solicitudData },{ primaryPool, secondaryPool }) => {
      let primaryConnection;
      try {
        primaryConnection = await primaryPool.getConnection();

        // Iniciar transacción
        await primaryConnection.beginTransaction();

        const {
          codCli,
          id_line,
          monto_sol,
          agencia,
          analista,
          iddestino,
          idsector,
          actividadeconomica,
          ciclo,
          primerpago,
          cuota,
          tipocred,
          peri,
          tasa_line,
          idsGarantias,
        } = solicitudData;

        // Validar que todos los campos estén presentes
        const campos = [
          { valor: codCli, nombre: "Código de cliente" },
          { valor: id_line, nombre: "Línea de crédito" },
          { valor: monto_sol, nombre: "Monto solicitado" },
          { valor: agencia, nombre: "Agencia" },
          { valor: analista, nombre: "Analista" },
          { valor: iddestino, nombre: "Destino" },
          { valor: idsector, nombre: "Sector" },
          { valor: actividadeconomica, nombre: "Actividad económica" },
          { valor: ciclo, nombre: "Ciclo" },
          { valor: primerpago, nombre: "Primer pago" },
          { valor: cuota, nombre: "No. de Cuota" },
          { valor: tipocred, nombre: "Tipo de crédito" },
          { valor: peri, nombre: "Tipo de periodo" },
        ];

        for (let campo of campos) {
          if (!campo.valor || campo.valor === 0 || campo.valor === "0") {
            throw new Error(
              `El campo ${campo.nombre} no puede estar vacío o contener 0.`
            );
          }
        }

        // Validar garantías
        if (!idsGarantias || idsGarantias.length === 0) {
          throw new Error("No se han seleccionado garantías.");
        }

        // Obtener código de agencia
        const [agenciaRows] = await primaryConnection.query(
          "SELECT cod_agenc FROM tb_agencia WHERE id_agencia = ?",
          [agencia]
        );

        if (agenciaRows.length === 0) {
          throw new Error(`No se encontró la agencia especificada: ${agencia}`);
        }

        const codigoagencia = agenciaRows[0].cod_agenc;

        // Generar código de crédito usando la función stored procedure
        const [codeResult] = await primaryConnection.query(
          "SELECT cre_crecodcta(?, '01') as ccodcta",
          [agencia]
        );

        const codigoCuenta = codeResult[0].ccodcta;

        if (!codigoCuenta || codigoCuenta === 0) {
          throw new Error("No se pudo generar el código de crédito");
        }

        // Preparar datos para inserción
        const fechaactual = new Date()
          .toISOString()
          .slice(0, 19)
          .replace("T", " ");
        const fechanula = "0000-00-00";
        const cestado = "A";
        const peripagcap = "1";
        const plazoRefi = "0";
        const tipoEnti = "INDI";

        // Insertar en cremcre_meta
        const [insertResult] = await primaryConnection.query(
          `
      INSERT INTO cremcre_meta (
        CCODCTA, CodCli, CCODPRD, CODAgencia, CodAnal, Cestado, 
        DfecSol, DFecDsbls, ActoEcono, CtipCre, NtipPerC, peripagcap, 
        Cdescre, CSecEco, PlazoRefi, DfecPago, MontoSol, TipoEnti, cuotassolicita, 
        NCiclo, NIntApro, fecha_operacion
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    `,
          [
            codigoCuenta,
            codCli,
            id_line,
            codigoagencia,
            analista,
            cestado,
            fechaactual,
            fechanula,
            actividadeconomica,
            tipocred,
            peri,
            peripagcap,
            iddestino,
            idsector,
            plazoRefi,
            primerpago,
            monto_sol,
            tipoEnti,
            cuota,
            ciclo,
            tasa_line,
            fechaactual,
          ]
        );

        // Insertar garantías
        for (const idGarantia of idsGarantias) {
          await primaryConnection.query(
            "INSERT INTO tb_garantias_creditos (id_cremcre_meta, id_garantia) VALUES (?, ?)",
            [codigoCuenta, idGarantia]
          );
        }

        // Commit de la transacción
        await primaryConnection.commit();

        return {
          success: true,
          message: "Solicitud de crédito creada exitosamente",
          codigo_credito: codigoCuenta,
        };
      } catch (error) {
        // Rollback en caso de error
        if (primaryConnection) {
          await primaryConnection.rollback();
        }
        console.error("Error creando solicitud de crédito:", error);

        return {
          success: false,
          message: error.message || "Error al crear la solicitud de crédito",
          codigo_credito: null,
        };
      } finally {
        if (primaryConnection) primaryConnection.release();
      }
    },
  },
};

module.exports = resolvers;
