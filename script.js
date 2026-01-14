document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.join-us-form');

    form.addEventListener('submit', function(event) {
        event.preventDefault(); // Предотвращаем стандартное поведение формы

        // Собираем данные из формы
        const firstName = document.getElementById('first_name').value.trim();
        const lastName = document.getElementById('last_name').value.trim();
        const email = document.getElementById('email').value.trim();

        // Создаём объект данных
        const formData = {
            first_name: firstName,
            last_name: lastName,
            email: email
        };

        // Преобразуем в JSON
        const jsonData = JSON.stringify(formData);

        // Отправляем данные на сервер
        fetch('form.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: jsonData
        })
            .then(response => response.json())
            .then(data => {
                // Обработка ответа сервера
                alert(data.message || 'Данные успешно отправлены!');
            })
            .catch((error) => {
                console.error('Ошибка:', error);
                alert('Произошла ошибка при отправке данных.');
            });
    });
});

