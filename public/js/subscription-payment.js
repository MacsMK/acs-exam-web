window.acodeSubscriptionPayment = function (config) {
    const defaultHolder = config.defaultHolder;

    return {
        paymentMethod: config.paymentMethod,
        cardHolderName: config.cardHolderName,
        cardNumber: '',
        cardExpiry: '',
        cardCvv: '',
        speiSenderBank: config.speiSenderBank,
        speiAccountHolder: config.speiAccountHolder,
        isSubmitting: false,
        clipboardMessage: '',

        init() {
            this.$nextTick(() => {
                const error = [this.$refs.paymentError, this.$refs.onboardingError].find((element) => element?.getClientRects().length);
                (error ?? this.$refs.onboardingTitle)?.focus();
            });
        },

        beginPayment(event) {
            if (this.isSubmitting) {
                event.preventDefault();
                return;
            }

            this.isSubmitting = true;
        },

        retryPayment() {
            const field = this.paymentMethod === 'spei' ? this.$refs.speiBank : this.$refs.cardNumber;
            field?.focus();
        },

        get cardNumberStatus() {
            const digits = this.cardNumber.replace(/\D/g, '');
            if (digits.length < 13) return 'El número de demostración debe tener entre 13 y 19 dígitos.';

            let sum = 0;
            for (let i = 0; i < digits.length; i++) {
                const digit = Number(digits[i]);
                const doubled = (digits.length - i) % 2 === 0 ? digit * 2 : digit;
                sum += doubled > 9 ? doubled - 9 : doubled;
            }

            return sum % 10 === 0 ? 'Formato de tarjeta válido.' : 'Revisa el número de la tarjeta de demostración.';
        },

        formatCardNumber() {
            const digits = this.cardNumber.replace(/\D/g, '').slice(0, 19);
            this.cardNumber = digits.match(/.{1,4}/g)?.join(' ') ?? '';
        },

        formatExpiry() {
            const digits = this.cardExpiry.replace(/\D/g, '').slice(0, 4);
            this.cardExpiry = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
        },

        formatCvv() {
            this.cardCvv = this.cardCvv.replace(/\D/g, '').slice(0, 4);
        },

        fillTestCard(button) {
            this.cardNumber = button.dataset.number;
            this.cardExpiry = button.dataset.expiry;
            this.cardCvv = button.dataset.cvv;
            if (this.cardHolderName.trim() === '') {
                this.cardHolderName = defaultHolder;
            }
            this.$nextTick(() => this.$refs.cardNumber?.focus());
        },

        async copyClabe(clabe) {
            this.clipboardMessage = '';
            try {
                await navigator.clipboard.writeText(clabe);
                this.clipboardMessage = 'CLABE copiada al portapapeles.';
            } catch {
                this.clipboardMessage = 'No se pudo copiar la CLABE. Selecciónala y cópiala manualmente.';
            }
        }
    };
};
