document.addEventListener('DOMContentLoaded', () => {
    const formBuscar  = document.querySelector('#form-buscar');
    const inputBuscar = document.querySelector('#input-buscar');
    const contenedor  = document.querySelector('#resultados');

    // Cambiar a 'api/buscador-vulnerable.php' para probar la version insegura
    const ENDPOINT = 'api/buscador-seguro.php';

    formBuscar.addEventListener('submit', async (e) => {
        e.preventDefault(); // Previene la recarga del navegador

        const query = inputBuscar.value.trim();

        if (query.length === 0) {
            contenedor.textContent = "Escribí algo para buscar.";
            return;
        }

        contenedor.textContent = "Buscando en la base de datos...";

        try {
            // Petición GET con parámetro Query String en la URL
            const res = await fetch(`${ENDPOINT}?q=${encodeURIComponent(query)}`);
            const resultado = await res.json();

            if (res.ok && resultado['estado'] === 'exito') {
                renderizarProductos(resultado['data']);
            } else {
                // si el back manda un mensaje de error lo mostramos
                contenedor.textContent = resultado['mensaje'] ?? "No se encontraron productos.";
            }
        } catch (error) {
            console.error(error);
            contenedor.textContent = "Error de conexión con el servidor.";
        }
    });

    function renderizarProductos(productos) {
        contenedor.innerHTML = ''; // Limpiamos el contenedor

        if (productos.length === 0) {
            contenedor.textContent = "Sin resultados.";
            return;
        }

        const fragmento = document.createDocumentFragment();
        productos.forEach(prod => {
            const card = document.createElement('div');
            card.className = 'card-producto';

            // Renderizado seguro en el DOM con textContent (evita XSS)
            const titulo = document.createElement('h4');
            titulo.textContent = prod['nombre'];

            const categoria = document.createElement('span');
            categoria.className = 'categoria';
            categoria.textContent = prod['categoria'];

            const precio = document.createElement('p');
            precio.textContent = `$${Number(prod['precio']).toLocaleString('es-AR')}`;

            const stock = document.createElement('small');
            stock.textContent = `Stock: ${prod['stock']}`;

            card.appendChild(titulo);
            card.appendChild(categoria);
            card.appendChild(precio);
            card.appendChild(stock);
            fragmento.appendChild(card);
        });

        contenedor.appendChild(fragmento);
    }
});
