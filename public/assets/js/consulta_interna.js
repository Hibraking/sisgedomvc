var URL_CI = document.body.dataset.urlBase || '';
function buscarCI() {
  var tipo = document.getElementById('tipoBusq').value,
    v = (document.getElementById('valorBusq').value || '').trim();
  if (v === '') {
    alert('Ingrese el número a consultar.');
    return;
  }
  window.location = tipo === 'interno' ?
    (URL_CI + '?codigo_doc=' + encodeURIComponent(v)) :
    (URL_CI + '?num_exp=' + encodeURIComponent(v));
}
document.getElementById('valorBusq').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    buscarCI();
  }
});

function consultarSug(tipo, valor) {
  window.location = tipo === 'interno' ?
    (URL_CI + '?codigo_doc=' + encodeURIComponent(valor)) :
    (URL_CI + '?num_exp=' + encodeURIComponent(valor));
}
