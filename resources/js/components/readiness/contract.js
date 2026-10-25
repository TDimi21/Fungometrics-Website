export const readinessFields = [
  ['sleep_quality','Sleep quality'], ['energy','Energy'], ['body_comfort','Overall body comfort'],
  ['arm_comfort','Arm comfort'], ['shoulder_comfort','Shoulder comfort'], ['elbow_comfort','Elbow comfort'],
  ['lower_body_comfort','Lower-body comfort'], ['calmness','Calmness / stress wellbeing'], ['motivation','Motivation'],
];
export const readinessContext = () => {
  const now = new Date();
  const date = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
  return {date, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'};
};
export const readinessQuery = ({date,timezone}) => `date=${encodeURIComponent(date)}&timezone=${encodeURIComponent(timezone)}`;
export const emptyReadiness = () => ({sleep_hours:'', pain_flag:false, notes:''});
export const readinessValid = answers => String(answers.sleep_hours).trim() !== '' && Number(answers.sleep_hours)>=0 && Number(answers.sleep_hours)<=24 && readinessFields.every(([key])=>Number.isInteger(Number(answers[key])) && Number(answers[key])>=1 && Number(answers[key])<=10);
export const readinessPayload = answers => ({...answers,sleep_hours:Number(answers.sleep_hours),...Object.fromEntries(readinessFields.map(([key])=>[key,Number(answers[key])]))});
