<?php

class Ahomtip
{
    private $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    public function getAllAhomtip()
    {
        return $this->db->selectAll('ahomtip');
    }

    public function getAhomtipById($id)
    {
        // return $this->db->selectDataID('ahomtip', "id_tipo", $id);
        return $this->selectAhomtipColumns(['*'],"id_tipo=?",[$id]);
    }
    public function selectAhomtipColumns($columns = ['*'], $condition = '', $params = [])
    {
        $selectedColumns = implode(',', $columns);
        $query = "SELECT $selectedColumns FROM ahomtip";

        if (!empty($condition)) {
            $query .= " WHERE $condition";
        }

        return $this->db->getAllResults($query, $params);
    }

    public function createAhomtip($data)
    {
        return $this->db->insert('ahomtip', $data);
    }

    public function updateAhomtip($id, $data)
    {
        $condition = "id_tipo = ?";
        $conditionParams = [$id];
        $this->db->update('ahomtip', $data, $condition, $conditionParams);
    }

    public function deleteAhomtip($id)
    {
        $condition = "id_tipo = :id";
        $params = [':id' => $id];
        $this->db->delete('ahomtip', $condition, $params);
    }

    public function getAhomtipByOfficeAndType($ccodofi, $ccodtip)
    {
        $query = "SELECT * FROM ahomtip WHERE ccodofi = :ccodofi AND ccodtip = :ccodtip";
        $params = [':ccodofi' => $ccodofi, ':ccodtip' => $ccodtip];
        return $this->db->getSingleResult($query, $params);
    }

    public function getActiveAhomtip()
    {
        $query = "SELECT * FROM ahomtip WHERE estado = 1";
        return $this->db->getAllResults($query);
    }

    public function updateAhomtipRate($id, $newRate)
    {
        $data = ['tasa' => $newRate];
        $condition = "id_tipo = :id";
        $conditionParams = [':id' => $id];
        $this->db->update('ahomtip', $data, $condition, $conditionParams);
    }

    public function searchAhomtip($searchTerm)
    {
        $query = "SELECT * FROM ahomtip WHERE nombre LIKE :searchTerm OR cdescripcion LIKE :searchTerm";
        $params = [':searchTerm' => "%$searchTerm%"];
        return $this->db->getAllResults($query, $params);
    }
}
