<template>
  <div class="flex flex-col">
    <div v-if="errorMessage !== null">
      <PaymentErrorBlock :message="errorMessage" />
    </div>

    <div v-if="isLoading" class="w-full flex items-center justify-center p-5">
      <BaseSpinner class="text-primary-500 h-10 w-10" />
    </div>

    <div class="payment" ref="paymentRef" v-show="!isLoading && errorMessage === null"></div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { usePaymentProviderStore } from '~/scripts/stores/payment-provider'
import PaymentErrorBlock from './PaymentErrorBlock.vue'
const { useRoute } = window.VueRouter

import { AdyenCheckout, Card } from '@adyen/adyen-web';
import '@adyen/adyen-web/styles/adyen.css';

const paymentRef = ref()
const route = useRoute()

const isLoading = ref(true)
const errorMessage = ref(null)
const paymentReceiptUrl = ref(null)

const emit = defineEmits(['disable', 'reload'])

const paymentProviderStore = usePaymentProviderStore()

onMounted(async () => {
  try {
    let res = await paymentProviderStore.generatePayment(route.params.company)


    if (res.data.error) {
      errorMessage.value = res.data.error.description
      showErrorMessage.value = true
      return
    }

    const checkout = await createAdyenCheckout(res.data);

    new Card(checkout, {
	    onError: () => {},
	  }).mount(paymentRef.value)

    isLoading.value = false
  } catch (e) {
    console.error(e)
    isLoading.value = false
  }
})

async function createAdyenCheckout(data) {
  const configuration = {
    clientKey: 'test_OACDBSEAUFEEPFQTIEUU6YE3RYOSPKD6',
    session: {
      id: data.id,
      sessionData: data.sessionData,
    },
    environment: "test", // Change to 'live' for production
    amount: data.amount,
    locale: data.shopperLocale,
    countryCode: data.countryCode,

    onPaymentCompleted: (result, _) => {
      console.log('Payment complete', JSON.stringify(result))

      try {
        paymentProviderStore
          .confirmTransaction(data.id, {
            payment_id: data.id,
            order_id: data.reference,
            company_id: route.params.company,
          })
          .then((res) => {
            paymentReceiptUrl.value = `/m/payments/pdf/${res.data.transaction.unique_hash}`
            emit('reload', paymentReceiptUrl.value)
          })
      } catch (error) {
        console.log('confirm transaction failed')
        console.error(error)
      }
    },
    onPaymentFailed: (result, _) => {
      errorMessage.value = "Payment failed"
      console.log('Payment failed', JSON.stringify(result))
    },
    onError: (error, component) => {
	    console.error(error.name, error.message, error.stack, component);
	  }
  };

  return AdyenCheckout(configuration);
}

</script>
