// schema.js - CORREGIDO: DUPLICADOS ELIMINADOS EN CLIENTEINPUT

const { gql } = require("apollo-server");

const typeDefs = gql`
  type DocumentFormat {
    id: Int!
    id_reporte: Int!
    nombre: String!
    file: String
  }

  type Credit {
    ccodcta: String
    nombre: String
    monsug: Float
    Saldo: Float
    estado: String
    eliminado: String
  }

  type ClientDetails {
    ccodcta: String
    nombre: String
  }

  type CreditDetails {
    nombrecli: String
    codcli: String
    codagencia: String
    codprod: String
    ccodcta: String
    monsug: Float
    interes: Float
    fecdesembolso: String
    cuotas: Int
    tipocred: String
    nomper: String
    saldocap: Float
    saldoint: Float
    id_fondo: String
    urlfoto: String
  }

  type ConfigCre {
    id: ID!
    config_name: String!
    estado: Int!
    comentario: String
  }

  type PaymentPlan {
    id: String!
    dfecven: String
    diasatraso: Int
    cestado: String
    numcuota: Int
    capital: Float
    interes: Float
    mora: Float
    ahorropro: Float
    otrospagos: Float
  }

  type LoginResponse {
    token: String
    user: User
    nombre: String
    apellido: String
    dpi: String
    estado: String
    puesto: String
    id_agencia: String
    cod_agenc: String
    nom_agencia: String
  }

  type User {
    id: String
    usu: String
  }

  type Licencia {
    id: Int
    uniqueId: String
    modelName: String
    model: String
    manufacturer: String
    deviceType: String
    estado: String
  }

  type Banco {
    id: String!
    nombre: String!
    estado: Int!
  }

  type CuentaBanco {
    id: String!
    numcuenta: String!
    id_nomenclatura: Int!
  }

  type Cliente {
    idcod_cliente: String!
    short_name: String!
    no_identifica: String!
    direccion: String!
    tel_no1: String!
    ciclo: Int!
  }

  type LineaCredito {
    id: String!
    codigo: String!
    nombre: String!
    descripcion: String!
    fondos: String!
    tasa: String!
    monto: String!
  }

  type Agencia {
    id: String!
    nombre: String!
  }

  type Analista {
    id: String!
    nombre: String!
  }

  type DestinoCredito {
    id: String!
    destino: String!
  }

  type SectorEconomico {
    id: String!
    nombre: String!
  }

  type ActividadEconomica {
    id: String!
    titulo: String!
  }

  type TipoCredito {
    abre: String!
    credito: String!
  }

  type TipoPeriodo {
    id: String!
    descripcion: String!
    dias: Int!
    cod_msplus: String!
  }

  type GarantiaCliente {
    codcli: String!
    idgar: String!
    idtipgar: String!
    nomtipgar: String!
    idtipdoc: String!
    nomtipdoc: String!
    descripcion: String!
    direccion: String!
    montogravamen: Float!
    nomcli: String
    direccioncli: String
  }

  type ClienteConGarantias {
    cliente: Cliente!
    garantias: [GarantiaCliente!]!
    tieneGarantias: Boolean!
  }

  # ============================================
  # NUEVOS TIPOS PARA ESTADO DE CUENTA
  # ============================================

  type ClienteCredito {
    ccodcta: String!
    codcli: String!
    dpi: String!
    nombre: String!
    direccion: String
    telefono: String
    monsug: Float!
    estado: String!
    fecha_desembolso: String
    tasa_interes: Float
    num_cuotas: Int
    tipo_credito: String
  }

  type EstadoCuentaMovimiento {
    dfecpro: String
    cnrocuo: Int
    nmonto: Float
    cnuming: String
    cconcep: String
    kp: Float
    interes: Float
    mora: Float
    ahoprg: Float
    otr: Float
    ctippag: String
  }

  # ============================================
  #  HISTORIAL 
  # ============================================

  type UserClient {
    idcod_cliente: String!
    short_name: String!
    date_birth: String
    fecha_alta: String
  }

  type UserPayment {
    ccodcta: String!
    dfecpro: String
    cconcep: String
    kp: Float!
    interes: Float!
    mora: Float!
    ahoprg: Float!
    otr: Float!
    boletabanco: String
    dfecmod: String
  }

  type ClienteHistorial {
    ccodcta: String!
    codcli: String!
    nombre: String!
    ciclo: Int!
    monsug: Float!
    tipocred: String!
    estado: String!
  }

  type PlanPagoItem {
    cnrocuo: Int!
    dfecven: String
    dfecpag: String
    diasatraso: Int!
    cestado: String!
    ncapita: Float!
    nintere: Float!
    cflag: String
  }

  type Query {
    searchCredits: [Credit]
    getClientDetails(ccodcta: String!): ClientDetails
    getCreditDetails(ccodcta: String!): CreditDetails
    searchClientesHistorial(searchTerm: String): [ClienteHistorial!]!
    getPlanPagos(ccodcta: String!): [PlanPagoItem!]!
    getConfigCre: [ConfigCre!]!
    getPaymentPlan(ccodcta: String!): [PaymentPlan!]!
    searchClients(searchTerm: String): [Cliente!]!
    searchLines(searchTerm: String): [LineaCredito!]!
    getAgencias: [Agencia!]!
    getAnalistas: [Analista!]!
    getDestinosCredito: [DestinoCredito!]!
    getSectoresEconomicos: [SectorEconomico!]!
    getActividadesEconomicas(sectorId: String): [ActividadEconomica!]!
    getTiposCredito: [TipoCredito!]!
    getTiposPeriodo(tipoCredito: String): [TipoPeriodo!]!
    getClienteGarantias(clienteId: String!): ClienteConGarantias
    me: User
    licencias: [Licencia!]
    licenciaDatos(
      uniqueId: String!
      manufacturer: String!
      model: String!
      modelName: String!
      deviceType: String!
    ): Licencia
    getBancos: [Banco!]!
    getCuentasBanco(bancoid: String!): [CuentaBanco!]!
    getDocumentFormat(reportId: Int!): DocumentFormat
    
    # ============================================
    # NUEVAS QUERIES PARA ESTADO DE CUENTA
    # ============================================
    searchClientesCreditos(searchTerm: String): [ClienteCredito!]!
    getEstadoCuenta(ccodcta: String!): [EstadoCuentaMovimiento!]!
    
    # ============================================
    # QUERIES PARA HISTORIAL 
    # ============================================
    getUserClients(userId: String!): [UserClient!]!
    getUserPayments(userId: String!): [UserPayment!]!
  }

  # ============================================
  # INPUTS 
  # ============================================

  input PaymentInput {
    ccodcta: String!
    numrecibo: String!
    metodoPago: String!
    fecpag: String!
    capital: Float!
    interes: Float!
    mora: Float!
    otros: Float!
    total: Float!
    boletabanco: String
    fecpagBANC: String
    bancoid: String
    cuentaid: String
    userId: String!
    agencyId: String!
    userName: String!
  }

  input SolicitudCreditoInput {
    codCli: String!
    id_line: String!
    monto_sol: Float!
    agencia: String!
    analista: String!
    iddestino: String!
    idsector: String!
    actividadeconomica: String!
    ciclo: Int!
    primerpago: String!
    cuota: Int!
    tipocred: String!
    peri: String!
    tasa_line: Float!
    idsGarantias: [String!]!
  }

  input ReferenciasInput {
    nombre: String!
    telefono: String!
    parentesco: String!
    direccion: String!
    referencia: String
  }

  input UbicacionInput {
    entidad_tipo: String!
    descripcion: String
    latitud: Float
    longitud: Float
    altitud: Float
    precision: Float
    direccion_texto: String
  }

  input ClienteInput {
    # Nombres
    primerNombre: String!
    segundoNombre: String
    tercerNombre: String
    
    # Apellidos
    primerApellido: String!
    segundoApellido: String
    tercerApellido: String
    
    # Datos personales
    genero: String!
    estadoCivil: String!
    profesion: String!
    email: String!
    conyugue: String
    telConyugue: String

    # Nacimiento
    fechaNacimiento: String!
    edad: Int!
    origen: String!
    paisNacimiento: String!
    departamentoNacimiento: String!
    municipioNacimiento: String!
    direccionNacimiento: String!
    
    # Documento
    documentoExtendido: String!
    tipoDocumento: String!
    numeroDocumento: String!
    tipoIdentTributaria: String!
    numeroNit: String!
    afiliacionIggs: String
    nacionalidad: String!

    # Domicilio (sección consolidada, sin duplicados)
    condicionVivienda: String!
    resideDesde: String!
    departamentoDomicilio: String!
    municipioDomicilio: String!
    direccionVivienda: String!
    referenciaVivienda: String
    zona: String!
    barrio: String!
    telefono1: String!
    telefono2: String!
    actuaNombrePropio: String!
    representante: String
    calidadActua: String!
    
    # Ubicación GPS (estandarizada a español)
    latitud: Float
    longitud: Float
    accuracy: Float
    timestamp: String
    
    # Datos adicionales (consolidados, sin duplicados)
    otraNacionalidad: String
    etnia: String!
    religion: String!
    educacion: String!
    relacionInstitucional: String!
    codigoInterno: String
    observaciones: String
    
    # Referencias (usando campos individuales; elimina si usas el array ReferenciasInput)
    ref1Nombre: String!
    ref1Telefono: String!
    ref1Parentesco: String!
    ref1Direccion: String
    ref1Referencia: String
    ref2Nombre: String
    ref2Telefono: String
    ref2Parentesco: String
    ref2Direccion: String
    ref2Referencia: String
    ref3Nombre: String
    ref3Telefono: String
    ref3Parentesco: String
    ref3Direccion: String
    ref3Referencia: String
    
    # Preguntas Si/No (usando String! para consistencia con "Si/No")
    sabeLeer: String!
    sabeEscribir: String!
    firma: String!
    esPep: String!
    esCpe: String!
    
    # Campos numéricos (usando String! si prefieres texto; cambia a Int! si es numérico puro)
    numeroHijos: String!
    dependencia: String!
    
    # Usuario y agencia
    userId: String!
    agencyId: String!
  }

  # ============================================
  # RESPONSES - TIPOS DE RESPUESTA
  # ============================================

  type SolicitudCreditoResponse {
    success: Boolean!
    message: String!
    codigo_credito: String
  }

  type ClienteResponse {
    success: Boolean!
    message: String!
    idcod_cliente: String
  }

  type PaymentResponse {
    success: Boolean!
    message: String!
    receiptNumber: String
    cuotaNumber: Int
  }

  # ============================================
  # MUTATIONS 
  # ============================================
  
  type Mutation {
    login(usu: String!, pass: String!): LoginResponse
    savePayment(paymentData: PaymentInput!): PaymentResponse
    crearSolicitudCredito(solicitudData: SolicitudCreditoInput!): SolicitudCreditoResponse
    crearCliente(input: ClienteInput!): ClienteResponse
  }
`;

module.exports = typeDefs;