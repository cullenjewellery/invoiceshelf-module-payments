<template>
  <div class="h-screen h-screen-ios overflow-y-auto min-h-0">
    <div class="bg-gradient-to-r from-primary-500 to-primary-400 h-5"></div>

    <div class="p-6 px-4 md:px-6 w-full md:w-auto md:max-w-xl mx-auto">
      <BasePageHeader title="Pay Invoice">
        <template #actions>
          <router-link
            v-if="paymentProviderStore.currentInvoice"
            :to="invoiceViewLink"
          >
            <BaseButton variant="primary-outline">
              {{ $t('general.view_invoice') }}
            </BaseButton>
          </router-link>
        </template>
      </BasePageHeader>

      <InvoiceInformationCard :invoice="paymentProviderStore.currentInvoice" />

      <div v-if="paymentReceiptUrl" class="bg-white shadow rounded-lg mt-6">
        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
          <PaymentSuccessBlock
            :invoice-view-link="invoiceViewLink"
            :payment-receipt-url="paymentReceiptUrl"
          />
        </div>
      </div>

      <div
        v-if="
          paymentProviderStore.currentInvoice &&
          paymentProviderStore.currentInvoice.paid_status !== 'PAID'
        "
        class="bg-white shadow rounded-lg mt-6"
      >
        <div class="px-4 py-5 sm:px-6">
          <h3 class="text-lg leading-6 font-medium text-gray-900">
            {{ paymentCardTitle }}
          </h3>
        </div>

        <div class="border-t border-gray-200 px-4 py-5 sm:px-6">
          <BaseInputGroup
            v-if="
              paymentProviderStore.activeProviders.length > 1 &&
              !isProviderDisabled
            "
            :label="$t('payment_providers.select_payment_method')"
            required
            class="mb-6 relative z-20"
          >
            <BaseMultiselect
              v-model="paymentProviderStore.selectedProvider"
              :options="paymentProviderStore.activeProviders"
              :can-deselect="true"
              :searchable="true"
              label="name"
              value-prop="name"
              object
            />
          </BaseInputGroup>

          <component
            :is="paymentComponent"
            :invoice-view-link="invoiceViewLink"
            @disable="disableProvider"
            @reload="reloadData"
          ></component>
        </div>
      </div>

      <div
        v-if="!customerLogo"
        class="flex items-center justify-center mt-6 text-gray-500 font-normal"
      >
        Powered by
        <a href="https://invoiceshelf.com/" target="_blank">
          <img :src="getLogo()" class="h-4 ml-1 mb-1" />
        </a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { usePaymentProviderStore } from '~/scripts/stores/payment-provider'
const { useRoute, useRouter } = window.VueRouter

import InvoiceInformationCard from '@/scripts/components/InvoiceInformationCard.vue'
import InvoiceShelfLogo from '@/static/img/logo-gray.png'

import RazorPay from '~/scripts/components/RazorPay.vue'
import StripePay from '~/scripts/components/StripePayment.vue'
import Paypal from '~/scripts/components/Paypal.vue'
import Adyen from '../components/Adyen.vue';
import PaymentSuccessBlock from '~/scripts/components/PaymentSuccessBlock.vue'

const route = useRoute()
const router = useRouter()

const isProviderDisabled = ref(false)
const paymentReceiptUrl = ref(null)
const isPaid = ref(false)

const paymentProviderStore = usePaymentProviderStore()
const { t } = useI18n()

paymentProviderStore.currentInvoice = null

fetchProviders()

loadInvoice()

function isCustomerPortalPage() {
  return route.name === 'invoice.portal.payment'
}

const invoiceViewLink = computed(() => {
  if (isCustomerPortalPage()) {
    return {
      name: 'customer.invoices.view',
      params: {
        company: paymentProviderStore.currentInvoice.company.slug,
        id: paymentProviderStore.currentInvoice.id,
      },
    }
  }

  return {
    name: 'invoice.public',
    params: { hash: route.params.hash },
  }
})

function reloadData(receiptUrl) {
  paymentReceiptUrl.value = receiptUrl
  loadInvoice()
}

async function loadInvoice() {
  let res = null
  if (isCustomerPortalPage()) {
    res = await axios.get(
      `/api/v1/${route.params.company}/customer/invoices/${route.params.id}`
    )
  } else {
    res = await axios.get(`/customer/invoices/${route.params.hash}`)
  }

  paymentProviderStore.currentInvoice = res.data.data
}

async function fetchProviders() {
  await paymentProviderStore.fetchActiveProviders(route.params.company)
}

const paymentComponent = computed(() => {
  if (!paymentProviderStore.selectedProvider) {
    return null
  }

  if (paymentProviderStore.selectedProvider.driver == 'razorpay')
    return RazorPay
  if (paymentProviderStore.selectedProvider.driver == 'stripe') return StripePay

  if (paymentProviderStore.selectedProvider.driver == 'paypal') return Paypal

  if (paymentProviderStore.selectedProvider.driver == 'adyen') return Adyen

  return null
})

const paymentCardTitle = computed(() => {
  return !isProviderDisabled.value
    ? t('payment_providers.enter_payment_details')
    : t('payment_providers.payment_status')
})

function getLogo() {
  return InvoiceShelfLogo
}

const customerLogo = computed(() => {
  if (window.customer_logo) {
    return window.customer_logo
  }

  return false
})

function disableProvider() {
  isProviderDisabled.value = true
}
</script>
