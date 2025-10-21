document.addEventListener('DOMContentLoaded', function () {
    // New code for calendar and modal
    const calendarEl = document.getElementById('calendar');
    const timeSlotsEl = document.getElementById('time-slots');
    const modal = document.getElementById('modal');
    const closeModal = document.querySelector('.close-button');
    const modalForm = document.getElementById('modal-form');
    const selectedDateInput = document.getElementById('selected-date');
    const selectedTimeInput = document.getElementById('selected-time');

    function fetchBookingsAndGenerateSlots(dateStr) {
        // Add a cache-busting parameter to the URL to get the latest bookings
        fetch(`bookings.json?v=${new Date().getTime()}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(bookings => {
                generateTimeSlots(dateStr, bookings);
                timeSlotsEl.classList.remove('hidden');
            })
            .catch(error => {
                console.error('Failed to load bookings:', error);
                // Even if bookings fail to load, show available slots
                generateTimeSlots(dateStr, []);
                timeSlotsEl.classList.remove('hidden');
            });
    }


    if (calendarEl && window.flatpickr) {
        flatpickr.localize(flatpickr.l10ns.ru);
        flatpickr(calendarEl, {
            inline: true,
            minDate: "today",
            // Disable Sundays
            disable: [
                function(date) {
                    // return true to disable
                    return (date.getDay() === 0);
                }
            ],
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates[0]) {
                    selectedDateInput.value = dateStr;
                    fetchBookingsAndGenerateSlots(dateStr);
                }
            },
        });
    }

    function generateTimeSlots(dateStr, bookings) {
        timeSlotsEl.innerHTML = '';
        const bookedSlotsForDate = bookings.filter(b => b.date === dateStr).map(b => b.time);

        // Show slots up to 17:00 because a 2-hour repair is assumed
        for (let i = 9; i <= 17; i++) {
            const time = `${i}:00`;
            const timeSlot = document.createElement('div');
            timeSlot.classList.add('time-slot');
            timeSlot.textContent = time;

            if (bookedSlotsForDate.includes(time)) {
                timeSlot.classList.add('disabled');
            } else {
                timeSlot.addEventListener('click', () => {
                    selectedTimeInput.value = time;
                    modal.classList.remove('hidden');
                });
            }
            timeSlotsEl.appendChild(timeSlot);
        }
    }

    if(closeModal) {
        closeModal.addEventListener('click', () => {
            modal.classList.add('hidden');
        });
    }

    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });

    if (modalForm) {
        modalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(modalForm);
            const submitButton = modalForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;

            let statusMessage = modal.querySelector('.status-message');
            if (!statusMessage) {
                statusMessage = document.createElement('small');
                statusMessage.className = 'status-message';
                statusMessage.style.display = 'block';
                statusMessage.style.marginTop = '10px';
                submitButton.insertAdjacentElement('afterend', statusMessage);
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Отправка...';
            statusMessage.textContent = '';

            fetch('telegram.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusMessage.textContent = 'Спасибо! Ваша заявка отправлена.';
                    statusMessage.style.color = 'green';
                    modalForm.reset();
                    
                    // Immediately update the UI to show the new booking
                    fetchBookingsAndGenerateSlots(selectedDateInput.value);

                    setTimeout(() => {
                         modal.classList.add('hidden');
                    }, 2000);
                } else {
                    statusMessage.textContent = 'Ошибка: ' + (data.message || 'Неизвестная проблема.');
                    statusMessage.style.color = 'red';
                     // If booking failed because slot is taken, refresh the slots from the server
                    if (data.message && data.message.includes('время уже занято')) {
                        fetchBookingsAndGenerateSlots(selectedDateInput.value);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusMessage.textContent = 'Сетевая ошибка.';
                statusMessage.style.color = 'red';
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
                setTimeout(() => {
                    if (statusMessage) statusMessage.textContent = '';
                }, 5000);
            });
        });
    }
});
