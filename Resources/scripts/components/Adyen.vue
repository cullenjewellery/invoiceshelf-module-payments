<template>
  <div class="flex flex-col">
    <div v-if="showErrorMessage">
      <PaymentErrorBlock :message="errorMessage" />
    </div>

    <div class="payment" ref="paymentRef"></div>
  </div>
</template>

<script setup>
import { onMounted, inject, ref } from 'vue'
import { usePaymentProviderStore } from '~/scripts/stores/payment-provider'
import PaymentErrorBlock from './PaymentErrorBlock.vue'
const { useRoute, useRouter } = window.VueRouter

import { AdyenCheckout, Card } from '@adyen/adyen-web';
import '@adyen/adyen-web/styles/adyen.css';

const paymentRef = ref()

const route = useRoute()
const router = useRouter()
const utils = inject('utils')

const isLoading = ref(true)
const showErrorMessage = ref(false)
let errorMessage = ref(null)
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

    onPaymentCompleted: (result, component) => {
      console.log('Payment complete', JSON.stringify(result))

      paymentProviderStore
        .confirmTransaction(data.transaction.unique_hash)
        .then((res) => {
          paymentReceiptUrl.value = `/m/payments/pdf/${data.transaction.unique_hash}`
          emit('reload', paymentReceiptUrl.value)
        })
    },
    onPaymentFailed: (result, component) => {
      console.log('Payment failed', JSON.stringify(result))
    },
    onError: (error, component) => {
	    console.error(error.name, error.message, error.stack, component);
	  }
  };

  return AdyenCheckout(configuration);
}

</script>
