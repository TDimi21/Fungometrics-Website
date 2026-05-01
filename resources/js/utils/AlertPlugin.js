import Swal from "sweetalert2"

const toast = Swal.mixin({
  toast: true,
  position: 'top-right',
  iconColor: 'white',
  customClass: {
    popup: 'colored-toast'
  },
  showConfirmButton: false,
  timer: 5000,
  timerProgressBar: true
});

const confirm = Swal.mixin({
  title: 'Are you sure?',
  text: "Yo close the actual session!",
  icon: 'warning',
  showCancelButton: true,
  confirmButtonColor: '#3085d6',
  cancelButtonColor: '#d33',
  confirmButtonText: 'Yes, close it!'
});

export {
  toast,
  confirm
}
