(()=>{"use strict";var e={1020:(e,n,t)=>{var r=t(1609),a=Symbol.for("react.element"),l=Symbol.for("react.fragment"),o=Object.prototype.hasOwnProperty,s=r.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};function c(e,n,t){var r,l={},c=null,u=null;for(r in void 0!==t&&(c=""+t),void 0!==n.key&&(c=""+n.key),void 0!==n.ref&&(u=n.ref),n)o.call(n,r)&&!i.hasOwnProperty(r)&&(l[r]=n[r]);if(e&&e.defaultProps)for(r in n=e.defaultProps)void 0===l[r]&&(l[r]=n[r]);return{$$typeof:a,type:e,key:c,ref:u,props:l,_owner:s.current}}n.Fragment=l,n.jsx=c,n.jsxs=c},1609:e=>{e.exports=window.React},4848:(e,n,t)=>{e.exports=t(1020)}},n={};function t(r){var a=n[r];if(void 0!==a)return a.exports;var l=n[r]={exports:{}};return e[r](l,l.exports,t),l.exports}t.n=e=>{var n=e&&e.__esModule?()=>e.default:()=>e;return t.d(n,{a:n}),n},t.d=(e,n)=>{for(var r in n)t.o(n,r)&&!t.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:n[r]})},t.o=(e,n)=>Object.prototype.hasOwnProperty.call(e,n);const r=window.wc.wcBlocksRegistry;var a=t(1609);const l=window.jQuery;var o=t.n(l);const s=window.wp.i18n;var i=t(4848);const c=(e,n)=>{const t=wc?.wcSettings?.getSetting(e+"_data",null);if(!t)throw new Error(e+" initialization data is not available");return t},u=e=>{var n;return null!==(n=c(e)?.can_make_payment)&&void 0!==n&&n},d=e=>{var n;return Object.entries(null!==(n=c(e)?.content_icons)&&void 0!==n?n:[]).map((([e,{src:n,alt:t}])=>({id:e,src:n,alt:t})))},v=e=>{var n;return null!==(n=c(e)?.content)&&void 0!==n?n:""},p=e=>{var n;return null!==(n=c(e)?.features)&&void 0!==n?n:[]},m=e=>{var n;return null!==(n=c(e)?.show_saved_cards)&&void 0!==n&&n},_=e=>{var n;return null!==(n=c(e)?.show_save_option)&&void 0!==n&&n},x=e=>{var n;return null!==(n=c(e)?.installments?.title_text)&&void 0!==n?n:""},h=e=>{var n;return null!==(n=c(e)?.installments?.one_solution_text)&&void 0!==n?n:""},y=e=>{var n;return null!==(n=c(e)?.recurring?.disclaimer_text)&&void 0!==n?n:""},f=({label:e,icons:n,components:t})=>{const{PaymentMethodLabel:r,PaymentMethodIcons:a}=t;return(0,i.jsxs)(i.Fragment,{children:[(0,i.jsx)(r,{text:e}),a&&n.length>0&&(0,i.jsx)(a,{icons:n,align:"right"})]})},w=({paymentMethodName:e,eventRegistration:n,components:t})=>{const{PaymentMethodIcons:r}=t,{onPaymentSetup:l}=n,s=d(e),u=(e=>{var n;return null!==(n=c(e)?.installments?.enabled)&&void 0!==n&&n})(e),p=(e=>{var n;return null!==(n=c(e)?.installments?.options)&&void 0!==n?n:[]})(e),m=(e=>{var n;return null!==(n=c(e)?.installments?.is_pago_dil)&&void 0!==n&&n})(e),_=(e=>{var n;return null!==(n=c(e)?.installments?.pago_dil_admin_url)&&void 0!==n?n:""})(e),f=(e=>{var n;return null!==(n=c(e)?.installments?.pago_dil_installment_amount_label)&&void 0!==n?n:""})(e),w=(e=>{var n;return null!==(n=c(e)?.recurring?.enabled)&&void 0!==n&&n})(e),[g,j]=(0,a.useState)((e=>{var n;return null!==(n=c(e)?.installments?.default_option)&&void 0!==n?n:""})(e)),[b,O]=(0,a.useState)(f);return(0,a.useEffect)((()=>l((()=>async function(){const e={type:"success",meta:{paymentMethodData:{}}};return u&&p.length>0&&(e.meta.paymentMethodData.nexi_xpay_number_of_installments=g),e}()))),[l,u,p,g]),(0,a.useEffect)((()=>{!m||!u||!p.length>0?O(""):o().ajax({type:"POST",data:{action:"calc_installments",installments:parseInt(g)},url:_+"admin-ajax.php",success:function(e){O(e.installmentsLabel)},complete:function(){}})}),[_,g,O,o()]),(0,i.jsxs)(i.Fragment,{children:[(0,i.jsx)("span",{children:v(e)}),r&&s.length>0&&(0,i.jsx)(r,{contentIcons:s,align:"right"}),u&&p.length>0&&(0,i.jsxs)("div",{className:"wc-gateway-nexi-xpay-block-checkout-additional-info",children:[(0,i.jsx)("div",{children:(0,i.jsx)("label",{className:"wc-gateway-nexi-xpay-block-checkout-row",children:x(e)})}),(0,i.jsxs)("select",{defaultValue:"",onChange:e=>j(e.target.value),children:[!m&&(0,i.jsx)("option",{value:"",children:h(e)}),p.map((e=>(0,i.jsx)("option",{value:e,children:e})))]}),m&&(0,i.jsx)("div",{className:"wc-gateway-nexi-xpay-block-checkout-row",children:(0,i.jsx)("span",{children:b})})]}),w&&(0,i.jsx)("div",{children:(0,i.jsx)("span",{children:y(e)})})]})};(0,r.registerPaymentMethod)(((e,n)=>{let t=()=>u(e);void 0!==n&&(t=()=>{u(e)&&n()});const r=(e=>{var n;return null!==(n=c(e)?.label)&&void 0!==n?n:""})(e),a=(e=>{var n;return Object.entries(null!==(n=c(e)?.icons)&&void 0!==n?n:[]).map((([e,{src:n,alt:t}])=>({id:e,src:n,alt:t})))})(e),l=d(e);return{name:e,content:(0,i.jsx)(w,{paymentMethodName:e}),label:(0,i.jsx)(f,{label:r,icons:a}),edit:(0,i.jsx)(w,{content:v(e),icons:l}),icons:a,canMakePayment:t,ariaLabel:(0,s.__)(r,"woocommerce-gateway-nexi-xpay"),supports:{showSavedCards:m(e),showSaveOption:_(e),features:p(e)}}})("xpay_npg_skrill"))})();