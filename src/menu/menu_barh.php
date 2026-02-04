<div class="row">
  <div class="col d-flex justify-content-start">
    <div class="col-lg-3">
      <div class="text"><?= $titlemodule ?? ""; ?></div>
    </div>
  </div>
  <div class="col d-flex justify-content-start">
    <div class="text"><?= $infoEnti['nomAge'] ?? ""; ?></div>
  </div>
  <div class="col d-flex justify-content-end">
    <nav>
      <div class="container mt-5">
        <div class="row">
          <div class="col d-flex justify-content-end">
            <button class="btn btn-light position-relative" id="bell">
              <i class="fas fa-bell"></i>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="id_con_alt">
                0
              </span>
            </button>
            <input type="number" id="auxcontadorpfpass" readonly style="display: none;">
            <div class="notifications" id="box">
              <h2>Notificaciones - <span>X</span></h2>
              <!-- NavTabs y Tab Content -->
              <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                  <a class="nav-link active" id="home-tab" data-bs-toggle="tab" href="#home" role="tab" aria-controls="home" aria-selected="true">Auth</a>
                </li>
                <li class="nav-item" role="presentation">
                  <a class="nav-link" id="plazo-tab" data-bs-toggle="tab" href="#plazofijo" role="tab" aria-controls="plazo" aria-selected="false">Otras</a>
                </li>
                <!-- <li class="nav-item" role="presentation">
                  <a class="nav-link" id="users-tab" data-bs-toggle="tab" href="#users" role="tab" aria-controls="users" aria-selected="false">Users</a>
                </li> -->
              </ul>
              <div class="tab-content" id="myTabContent" style="max-height: 40rem; overflow-y: auto;">
                <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                  <div id="notificationsContainer1" style="min-height: 3rem;">
                  </div>
                </div>
                <div class="tab-pane fade" id="plazofijo" role="tabpanel" aria-labelledby="plazo-tab">
                  <div id="notificationsContainer2" style="min-height: 3rem;">
                  </div>
                </div>
                <div class="tab-pane fade" id="users" role="tabpanel" aria-labelledby="users-tab">
                  <div id="notificationsContainer3" style="min-height: 3rem;">
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>
  </div>
</div>
<?php
