document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.buy-coins-page');
    if (!page) {
        return;
    }

    const verifyUrl = page.dataset.verifyUrl || '';
    const csrfToken = page.dataset.csrfToken || '';
    const redirectUrl = page.dataset.redirectUrl || '';

    if (!window.paypal || !verifyUrl) {
        return;
    }

    document.querySelectorAll('.buy-coins-paypal-container').forEach(container => {
        const packageId = container.dataset.packageId || '';
        const packageCoins = container.dataset.packageCoins || '0';
        const packagePrice = container.dataset.packagePrice || '0.00';

        window.paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'paypal',
                height: 45,
            },
            createOrder(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        description: `WOC Coins - Paquete ${packageCoins}`,
                        amount: {
                            currency_code: 'USD',
                            value: packagePrice,
                        },
                    }],
                });
            },
            onApprove(data, actions) {
                return actions.order.capture().then(() => {
                    window.alert('Pago procesado! Verificando con el servidor...');

                    const formData = new FormData();
                    formData.append('orderID', data.orderID);
                    formData.append('package_id', packageId);
                    formData.append('csrf_token', csrfToken);

                    return fetch(verifyUrl, {
                        method: 'POST',
                        body: formData,
                    })
                        .then(response => response.json())
                        .then(payload => {
                            if (!payload.success) {
                                window.alert(`Error: ${payload.error || 'Error al procesar el pago'}`);
                                return;
                            }

                            window.alert(`¡Pago exitoso! Has recibido ${payload.coins} WOC Coins.`);
                            if (redirectUrl) {
                                window.location.href = redirectUrl;
                            }
                        })
                        .catch(error => {
                            console.error(error);
                            window.alert('Error al procesar el pago');
                        });
                });
            },
            onError(error) {
                console.error('PayPal Error:', error);
                window.alert('Hubo un error con el pago. Por favor intenta de nuevo.');
            },
        }).render(`#${container.id}`);
    });
});