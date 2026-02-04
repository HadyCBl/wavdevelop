/**
 * Módulo para la vista view002 (Crear cuentas por cobrar)
 */
export class View002Module {
  constructor() {
    this.table = null;
    this.periodoSelect = null;
  }

  /**
   * Inicializa el caso create_periodos
   */
  initCreatePeriodos() {
    console.log('Inicializando create_periodos');
    startValidationGeneric('#periodoForm');
  }

  /**
   * Inicializa el caso create_account
   */
  initCreateAccount() {
    console.log('Inicializando create_account');
    
    // Inicializar DataTable
    const columns = [
      { data: 'codigo_cliente', className: 'text-left' },
      { data: 'nombre', className: 'text-left' },
      { data: 'identificacion' },
      {
        data: null,
        title: 'Acción',
        orderable: false,
        searchable: false,
        render: (data, type, row) => {
          return `<button class="btn btn-soft btn-success" 
                    onclick="window.view002Module.selectClient('${row.codigo_cliente}', '${row.nombre}', '${row.identificacion}')">
                    Seleccionar
                  </button>`;
        }
      }
    ];

    this.table = initServerSideDataTable('#clientesTable', 'clientes_all', columns, {
      onError: (xhr, error, thrown) => {
        Swal.fire({
          icon: 'error',
          title: 'Error al cargar clientes',
          text: 'Por favor, intente nuevamente'
        });
      }
    });

    // Inicializar TomSelect
    this.periodoSelect = initTomSelect('#periodo_id', {
      placeholder: 'Buscar período...',
      onChange: (value) => this.onPeriodoChange(value)
    });

    // Validación del formulario
    startValidationGeneric('#accountForm');
  }

  /**
   * Maneja el cambio de período
   */
  onPeriodoChange(value) {
    console.log('Período seleccionado:', value);
    
    if (!this.periodoSelect) return;
    
    const option = this.periodoSelect.options[value];
    if (option && option.$option) {
      const tasaInteres = option.$option.data('tasa-interes');
      const fechaInicio = option.$option.data('fecha-inicio');
      const fechaFin = option.$option.data('fecha-fin');

      fillFormFields({
        '#tasa_interes': tasaInteres,
        '#fecha_inicio_cuenta': fechaInicio,
        '#fecha_vencimiento': fechaFin
      });
    }
  }

  /**
   * Selecciona un cliente y actualiza los campos
   */
  selectClient(codigo, nombre, identificacion) {
    console.log('Cliente seleccionado:', { codigo, nombre, identificacion });
    
    fillFormFields({
      '#cliente_codigo_display': codigo,
      '#cliente_nombre_display': nombre,
      '#cliente_identificacion_display': identificacion
    });

    // Recargar la vista con el cliente seleccionado
    printdiv2('#cuadro', codigo);
    my_modal_1.close();

    Swal.fire({
      icon: 'success',
      title: 'Cliente seleccionado',
      text: `${nombre} - ${identificacion}`,
      timer: 2000,
      showConfirmButton: false,
      toast: true,
      position: 'top-end'
    });
  }

  /**
   * Inicializa el módulo basado en el caso actual
   */
  init() {
    const condi = document.getElementById('condi')?.value;
    
    console.log('Inicializando View002Module con condi:', condi);

    switch (condi) {
      case 'create_periodos':
        this.initCreatePeriodos();
        break;
      case 'create_account':
        this.initCreateAccount();
        break;
      default:
        console.warn('Caso no reconocido:', condi);
    }
  }

  /**
   * Limpia recursos al destruir el módulo
   */
  destroy() {
    if (this.table) {
      this.table.destroy();
      this.table = null;
    }
    if (this.periodoSelect) {
      this.periodoSelect.destroy();
      this.periodoSelect = null;
    }
  }
}