<template>
  <div class="flex flex-col">
    <div v-if="showErrorMessage">
      <PaymentErrorBlock :message="errorMessage" />
    </div>
    <BaseButton
      v-else
      variant="primary"
      type="submit"
      class="mt-4 w-full justify-center"
      :loading="isLoading"
      @click="handleSubmit"
    >
      <template #left="slotProps">
        <BaseIcon name="CreditCardIcon" :class="slotProps.class" />
      </template>

      {{
        $t('payment_providers.pay_with_razorpay', {
          amount: utils.formatMoney(orderData.order.amount, orderData.currency),
        })
      }}
    </BaseButton>
  </div>
</template>

<script setup>
import axios from 'axios'
import { onMounted, reactive, watchEffect, inject, ref } from 'vue'
import { usePaymentProviderStore } from '~/scripts/stores/payment-provider'
import PaymentSuccessBlock from './PaymentSuccessBlock.vue'
import PaymentErrorBlock from './PaymentErrorBlock.vue'
const { useRoute, useRouter } = window.VueRouter

const route = useRoute()
const router = useRouter()
const utils = inject('utils')

const isLoading = ref(true)
const showErrorMessage = ref(false)
let errorMessage = ref(null)
const paymentReceiptUrl = ref(null)
const emit = defineEmits(['disable', 'reload'])

let orderData = reactive({
  order: {
    id: '',
    amount: '',
  },
  currency: null,
  key: '',
})

const paymentProviderStore = usePaymentProviderStore()

onMounted(() => {
  let recaptchaScript = document.createElement('script')
  recaptchaScript.setAttribute(
    'src',
    'https://checkout.razorpay.com/v1/checkout.js'
  )
  document.head.appendChild(recaptchaScript)

  getData()
})

async function getData() {
  try {
    let res = await paymentProviderStore.generatePayment(route.params.company)
    
    if (res.data.error) {
      errorMessage.value = res.data.error.description
      showErrorMessage.value = true
      return
    }

    Object.assign(orderData.order, res.data.order)
    orderData.key = res.data.key
    orderData.currency = res.data.currency
    isLoading.value = false
  } catch (e) {
    console.error(e)
    isLoading.value = false
  }
}

function handleSubmit() {
  emit('disable', true)

  var rzp1 = new Razorpay({
    key: orderData.key,
    currency: orderData.currency,
    amount: orderData.order.amount,
    order_id: orderData.order.id,
    handler: function (response) {
      paymentProviderStore
        .confirmTransaction(response.razorpay_order_id, {
          payment_id: response.razorpay_payment_id,
          order_id: response.razorpay_order_id,
          signature: response.razorpay_signature,
          company_id: route.params.company,
        })
        .then((res) => {
          paymentReceiptUrl.value = `/m/payments/pdf/${res.data.transaction.unique_hash}`
          emit('reload', paymentReceiptUrl.value)
        })
    },
  })
  rzp1.on('payment.failed', function (response) {
    console.log(response.error.metadata.order_id)
    console.log(response.error.metadata.payment_id)
  })

  rzp1.open()
}
</script>
