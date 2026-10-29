// Keep existing integer-foot storage; yards may be entered to three decimal places.
export function longTossFeet(value,unit='ft') {
 const text=String(value??'').trim();
 if(!['ft','yd'].includes(unit)||!/^\d+(\.\d{1,3})?$/.test(text))return null;
 const amount=Number(text),feet=amount*(unit==='yd'?3:1);
 if(!Number.isFinite(feet)||feet<=0||feet>500||(unit==='ft'&&!Number.isInteger(amount)))return null;
 return Math.round(feet)||null;
}
