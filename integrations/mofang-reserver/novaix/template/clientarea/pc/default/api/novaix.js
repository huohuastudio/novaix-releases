function novaix_getHostList(params) {
  return Axios.get("/renovaix/host", { params: params });
}

function novaix_getHostDetail(id) {
  return Axios.get("/renovaix/host/" + id + "/configoption");
}

function novaix_provision(id, func, data) {
  return Axios.post("/renovaix/host/" + id + "/provision/" + func, data);
}

function novaix_getVnc(id, data) {
  return Axios.post("/renovaix/host/" + id + "/provision/vnc", data);
}

function novaix_getStatus(id) {
  return Axios.post("/renovaix/host/" + id + "/provision/status");
}
