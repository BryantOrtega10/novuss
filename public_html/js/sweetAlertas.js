function retornarAlerta(titulo, texto, nombreIcono, textoBoton, funcionCallback, parametroSesion) {
    const objOpciones = {
        title: titulo,
        text: texto,
        icon: nombreIcono,
        confirmButtonText: textoBoton,
        allowOutsideClick: false
    };
    // Si no enviamos una funcion, es porque la respuesta es incorrecta
    if (typeof funcionCallback != 'undefined' && funcionCallback != null) {
        
        Swal.fire(objOpciones).then(result => {
            funcionCallback(parametroSesion)
            }
        );
    } else {
        Swal.fire(objOpciones);
    }
}