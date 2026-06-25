<script setup>
import { reactive, ref, watch } from "vue";
import defaultImg from "../../assets/img/login/assteslogin/updatedlogo.png";

const defaultImage = defaultImg;

const props = defineProps({
  modelValue: [String, Number, Boolean, Object],
  label: {
    type: String,
    required: true,
  },
  inputClasses: {
    type: String,
    required: false,
  },
});

const emit = defineEmits(["update:modelValue"]);

// Dedicated ref to the hidden <input type="file"> element. This MUST be separate
// from modelValue — modelValue is the selected File (or, in edit forms, the existing
// avatar URL string for display). Clicking/resetting always acts on this element.
const fileInput = ref(null);

// Show the existing image when modelValue is a URL string; otherwise the default.
const image = reactive({
  src:
    typeof props.modelValue === "string" && props.modelValue
      ? props.modelValue
      : defaultImage,
});

const openFilePicker = () => {
  fileInput.value?.click();
};

const onFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    image.src = URL.createObjectURL(file);
    emit("update:modelValue", file); // v-model receives the File object
  }
};

const resetInputFile = () => {
  if (fileInput.value) fileInput.value.value = null;
  image.src = defaultImage;
  emit("update:modelValue", "");
};

// Edit forms hydrate the v-model with the existing avatar URL after mount — reflect
// that in the preview. A File model (newly picked) is handled by onFileChange, so
// only react to string URLs / clears here.
watch(
  () => props.modelValue,
  (val) => {
    if (typeof val === "string" && val) image.src = val;
    else if (val === "" || val == null) image.src = defaultImage;
  },
);
</script>

<template>
  <slot>
    <form @submit.prevent>
      <div class="w-full relative">
        <input
          ref="fileInput"
          accept="image/*"
          class="hidden"
          type="file"
          @change="onFileChange"
        />
      </div>
      <div class="w-full h-full">
        <div class="flex justify-between items-center">
          <p class="image-input-label text-fungo-darkblue text-lg">{{ props.label }}</p>
          <div>
            <button type="button" class="image-edit-btn bg-[#01CDCC] rounded-lg p-3" @click="openFilePicker">
              <img alt="Edit picture" src="@/assets/img/icons/i-edit.svg" class="image-edit-icon" />
            </button>
            <button
              type="button"
              class="image-remove-btn bg-fungo-red rounded-lg p-3 ml-1"
              @click="resetInputFile"
            >
              <img alt="Remove picture" src="@/assets/img/icons/i-remove.svg" />
            </button>
          </div>
        </div>

        <div
          :class="inputClasses"
          class="image-preview-panel bg-white rounded-md border border-fungo-darkblue min-h-[90px] mt-3.5 py-7 flex items-center justify-center overflow-hidden"
        >
          <img
            v-if="image.src == ''"
            :src="defaultImage"
            alt="Picture"
            class="object-center object-contain max-h-full max-w-full mx-auto"
          />
          <img
            v-else
            ref="img-source-data"
            :class="{
              'w-36 h-36 object-cover rounded-full border-[11px] border-[#D9D9D9]':
                image.src != defaultImage,
            }"
            :src="image.src"
            alt="Picture"
            class="object-center object-contain max-h-full max-w-full mx-auto"
          />
        </div>
      </div>
    </form>
  </slot>
</template>

<style scoped>
.image-edit-icon {
  filter: brightness(0) invert(1);
}
</style>
