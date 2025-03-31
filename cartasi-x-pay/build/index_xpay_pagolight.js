(()=>{"use strict";var e={1020:(e,t,n)=>{var a=n(1609),r=Symbol.for("react.element"),l=Symbol.for("react.fragment"),o=Object.prototype.hasOwnProperty,s=a.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};function c(e,t,n){var a,l={},c=null,u=null;for(a in void 0!==n&&(c=""+n),void 0!==t.key&&(c=""+t.key),void 0!==t.ref&&(u=t.ref),t)o.call(t,a)&&!i.hasOwnProperty(a)&&(l[a]=t[a]);if(e&&e.defaultProps)for(a in t=e.defaultProps)void 0===l[a]&&(l[a]=t[a]);return{$$typeof:r,type:e,key:c,ref:u,props:l,_owner:s.current}}t.Fragment=l,t.jsx=c,t.jsxs=c},1609:e=>{e.exports=window.React},4848:(e,t,n)=>{e.exports=n(1020)}},t={};function n(a){var r=t[a];if(void 0!==r)return r.exports;var l=t[a]={exports:{}};return e[a](l,l.exports,n),l.exports}n.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return n.d(t,{a:t}),t},n.d=(e,t)=>{for(var a in t)n.o(t,a)&&!n.o(e,a)&&Object.defineProperty(e,a,{enumerable:!0,get:t[a]})},n.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);const a=window.wc.wcBlocksRegistry;var r=n(1609);const l=window.jQuery;var o=n.n(l);const s=window.wp.i18n;var i=n(4848);const c=(e,t)=>{const n=wc?.wcSettings?.getSetting(e+"_data",null);if(!n)throw new Error(e+" initialization data is not available");return n},u=e=>{var t;return null!==(t=c(e)?.can_make_payment)&&void 0!==t&&t},d=e=>{var t;return Object.entries(null!==(t=c(e)?.content_icons)&&void 0!==t?t:[]).map((([e,{src:t,alt:n}])=>({id:e,src:t,alt:n})))},v=e=>{var t;return null!==(t=c(e)?.content)&&void 0!==t?t:""},p=e=>{var t;return null!==(t=c(e)?.features)&&void 0!==t?t:[]},m=e=>{var t;return null!==(t=c(e)?.show_saved_cards)&&void 0!==t&&t},_=e=>{var t;return null!==(t=c(e)?.show_save_option)&&void 0!==t&&t},x=e=>{var t;return null!==(t=c(e)?.installments?.title_text)&&void 0!==t?t:""},h=e=>{var t;return null!==(t=c(e)?.installments?.one_solution_text)&&void 0!==t?t:""},y=e=>{var t;return null!==(t=c(e)?.recurring?.disclaimer_text)&&void 0!==t?t:""},f=({label:e,icons:t,components:n})=>{const{PaymentMethodLabel:a,PaymentMethodIcons:r}=n;return(0,i.jsxs)(i.Fragment,{children:[(0,i.jsx)(a,{text:e}),r&&t.length>0&&(0,i.jsx)(r,{icons:t,align:"right"})]})},w=({paymentMethodName:e,eventRegistration:t,components:n})=>{const{PaymentMethodIcons:a}=n,{onPaymentSetup:l}=t,s=d(e),u=(e=>{var t;return null!==(t=c(e)?.installments?.enabled)&&void 0!==t&&t})(e),p=(e=>{var t;return null!==(t=c(e)?.installments?.options)&&void 0!==t?t:[]})(e),m=(e=>{var t;return null!==(t=c(e)?.installments?.is_pago_dil)&&void 0!==t&&t})(e),_=(e=>{var t;return null!==(t=c(e)?.installments?.pago_dil_admin_url)&&void 0!==t?t:""})(e),f=(e=>{var t;return null!==(t=c(e)?.installments?.pago_dil_installment_amount_label)&&void 0!==t?t:""})(e),w=(e=>{var t;return null!==(t=c(e)?.recurring?.enabled)&&void 0!==t&&t})(e),[g,j]=(0,r.useState)((e=>{var t;return null!==(t=c(e)?.installments?.default_option)&&void 0!==t?t:""})(e)),[b,O]=(0,r.useState)(f);return(0,r.useEffect)((()=>l((()=>async function(){const e={type:"success",meta:{paymentMethodData:{}}};return u&&p.length>0&&(e.meta.paymentMethodData.nexi_xpay_number_of_installments=g),e}()))),[l,u,p,g]),(0,r.useEffect)((()=>{!m||!u||!p.length>0?O(""):o().ajax({type:"POST",data:{action:"calc_installments",installments:parseInt(g)},url:_+"admin-ajax.php",success:function(e){O(e.installmentsLabel)},complete:function(){}})}),[_,g,O,o()]),(0,i.jsxs)(i.Fragment,{children:[(0,i.jsx)("span",{children:v(e)}),a&&s.length>0&&(0,i.jsx)(a,{contentIcons:s,align:"right"}),u&&p.length>0&&(0,i.jsxs)("div",{className:"wc-gateway-nexi-xpay-block-checkout-additional-info",children:[(0,i.jsx)("div",{children:(0,i.jsx)("label",{className:"wc-gateway-nexi-xpay-block-checkout-row",children:x(e)})}),(0,i.jsxs)("select",{defaultValue:"",onChange:e=>j(e.target.value),children:[!m&&(0,i.jsx)("option",{value:"",children:h(e)}),p.map((e=>(0,i.jsx)("option",{value:e,children:e})))]}),m&&(0,i.jsx)("div",{className:"wc-gateway-nexi-xpay-block-checkout-row",children:(0,i.jsx)("span",{children:b})})]}),w&&(0,i.jsx)("div",{children:(0,i.jsx)("span",{children:y(e)})})]})};(0,a.registerPaymentMethod)(((e,t)=>{let n=()=>u(e);void 0!==t&&(n=()=>{u(e)&&t()});const a=(e=>{var t;return null!==(t=c(e)?.label)&&void 0!==t?t:""})(e),r=(e=>{var t;return Object.entries(null!==(t=c(e)?.icons)&&void 0!==t?t:[]).map((([e,{src:t,alt:n}])=>({id:e,src:t,alt:n})))})(e),l=d(e);return{name:e,content:(0,i.jsx)(w,{paymentMethodName:e}),label:(0,i.jsx)(f,{label:a,icons:r}),edit:(0,i.jsx)(w,{content:v(e),icons:l}),icons:r,canMakePayment:n,ariaLabel:(0,s.__)(a,"woocommerce-gateway-nexi-xpay"),supports:{showSavedCards:m(e),showSaveOption:_(e),features:p(e)}}})("xpay_pagolight"))})();