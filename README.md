# AA 21 - Buscador seguro con PDO

**Materia:** Paradigmas y Lenguajes de Programación III
**Carrera:** Ingeniería en Sistemas - 3er año

Módulo backend en PHP que busca productos en MySQL usando PDO con consultas preparadas, y un frontend en JavaScript vanilla que consume la API con `fetch`.

## Estructura

```
buscador-vulnerable/
├── api/
│   ├── buscador-vulnerable.php   -> el que vino en el zip (lo dejé para comparar)
│   └── buscador-seguro.php       -> el que hice yo, con prepare()
├── config/
│   └── conexion.php              -> conexión PDO
├── css/styles.css
├── js/app.js                     -> fetch al endpoint seguro
├── sql/base_datos.sql            -> script de la tabla productos
└── index.html
```

## Cómo correrlo

1. Abrir XAMPP y prender **Apache** y **MySQL**.
2. Entrar a phpMyAdmin (botón *Admin* de MySQL) y ejecutar `sql/base_datos.sql`. Si ya tienen la base `clase_backend` de la clase pasada, solo se crea la tabla.
3. Copiar la carpeta `buscador-vulnerable` dentro de `C:\xampp\htdocs`.
4. Entrar a `http://localhost/buscador-vulnerable/`.

> En `config/conexion.php` dejé la contraseña vacía porque XAMPP viene así. El zip traía `'root'` y además la línea de `$host` estaba comentada sin querer (quedó pegada al comentario de arriba), así que la conexión tiraba error. Lo corregí.

## Análisis del buscador vulnerable

El problema está en esta línea:

```php
$sql = "SELECT * FROM productos WHERE nombre LIKE '%" . $q . "%'";
$resultado = $pdo->query($sql);
```

Lo que escribe el usuario se concatena directo dentro del string SQL. O sea, el motor no tiene forma de distinguir qué parte es la consulta que escribió el programador y qué parte es el dato que mandó el usuario. Si el texto trae una comilla simple, se cierra el string del LIKE y lo que sigue pasa a ser interpretado como SQL. Eso es la inyección SQL.

Probándolo vi que así se puede cambiar el `WHERE` para que traiga todos los registros, y peor, agregar otra consulta con `UNION` y mostrar en las tarjetas datos que no son productos (usuario de la base, nombre de la base, versión del servidor). Con eso alguien podría ir leyendo otras tablas.

Algo que me llamó la atención es que `conexion.php` ya tiene `ATTR_EMULATE_PREPARES => false`, pero no sirve de nada porque se usa `query()` y no `prepare()`. La configuración está bien, el error es cómo se arma la consulta.

Otra cosa: si la conexión fallaba, se le devolvía al cliente `$e->getMessage()`, que muestra info interna del servidor.

## La solución (`buscador-seguro.php`)

```php
$sql = "SELECT id, nombre, categoria, precio, stock
        FROM productos
        WHERE nombre LIKE :nombre OR categoria LIKE :categoria
        ORDER BY nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':nombre', $qLike, PDO::PARAM_STR);
$stmt->bindValue(':categoria', $qLike, PDO::PARAM_STR);
$stmt->execute();
```

Con `prepare()` la estructura de la consulta se manda primero al servidor y queda fija. Después el valor viaja por separado en los placeholders (`:nombre`), entonces MySQL lo trata siempre como un dato y nunca como código. Por más comillas que tenga el texto, termina buscando eso literal en el LIKE.

Además agregué:

- **Validaciones:** si la búsqueda viene vacía o tiene más de 100 caracteres (el largo de `nombre`) devuelve 400 sin consultar la base.
- **Escape de comodines del LIKE:** con `addcslashes($q, '%_\\')`. Esto no es por la inyección (eso ya lo resuelve el prepare), es para que si alguien busca `%` no se traiga toda la tabla.
- **Solo GET:** cualquier otro método devuelve 405.
- **Errores genéricos:** el `PDOException` se guarda con `error_log()` y al cliente solo le llega "Error interno del servidor".
- **SELECT con columnas explícitas** en vez de `SELECT *`.
- **Búsqueda también por categoría**, porque el placeholder decía "nombre o descripción" pero la tabla no tiene descripción.

Probé lo mismo que había roto el vulnerable contra el endpoint seguro y devuelve `total: 0`, sin errores ni datos de más. Las búsquedas normales (`mouse`, `perif`, `monitor`) andan igual que antes.

## Frontend

En `js/app.js` cambié el endpoint a `buscador-seguro.php` (está en una constante `ENDPOINT` por si se quiere probar el otro), muestro el mensaje de error que manda el back y agregué categoría y stock a las tarjetas. El renderizado sigue usando `textContent` y no `innerHTML`, así si en la base hubiera algo con HTML no se ejecuta (evita XSS).
