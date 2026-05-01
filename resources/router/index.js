import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../js/store/auth";
import { useUserStore } from "../js/store/user";

//Guest components
const Welcome = () => import("@/pages/Welcome.vue");

const PasswordRecoverEmail = () => import('@/pages/recover-password/Email.vue');
const PasswordRecoverPassword = () => import('@/pages/recover-password/Password.vue');
const LoginCoach = () => import("@/pages/login/Coach.vue");
const LoginPlayer = () => import("@/pages/login/Player.vue");
const RegisterCoach = () => import("@/pages/register/Coach.vue");
const RegisterPlayer = () => import("@/pages/register/Player.vue");
const RegisterComplete = () => import('@/pages/register/Complete.vue');
const DashBoard = () => import("@/pages/dashboard/Index.vue");
const IndexTrainingPage = () => import("@/pages/training/CreateTraining.vue");
const IndexTrainingMode = () =>
	import("@/pages/training/CreateTrainingMode.vue");
const IndexTrainingCage = () =>
	import("@/pages/training/CreateTrainingCage.vue");
const IndexTrainingABPage = () =>
	import("@/pages/training/CreateLiveABTraining.vue");
const TrackBatting = () => import("@/pages/training/Batting.vue");
const TrackBullpen = () => import("@/pages/training/Bullpen.vue");
const TrackTrainingMode = () => import("@/pages/training/TrainingMode.vue");
const TrackTrainingCage = () => import("@/pages/training/TrainingCage.vue");
const Roster = () => import("@/pages/roster/HomeRoster.vue");
const Manage = () => import("@/pages/manage/HomeManage.vue");
const CreateTeam = () => import("@/pages/manage/CreateTeam.vue");
const EditProfile = () => import("@/pages/profile/EditProfile.vue");
const EditProfilePlayer = () => import("@/pages/profile/EditProfilePlayer.vue");
const ChangePassword = () => import("@/pages/profile/ChangePassword.vue");
const EditPlayer = () => import("@/pages/roster/EditPlayer.vue");
const TrackLiveAB = () => import("@/pages/training/LiveAB.vue");

//layout
//Authenticated
const routes = [
	{
		name: "index",
		path: "/",
		component: Welcome,
		meta: { guest: true },
	},
  {
    name: 'password.forgot',
    path: '/forgot-password',
    component: PasswordRecoverEmail,
		meta: { guest: true },
  },
  {
    name: 'password.recover',
    path: '/password/reset/:token',
    component: PasswordRecoverPassword,
		meta: { guest: true },
		props: true,
  },
	{
		name: "register.coach",
		path: "/register/coach",
		component: RegisterCoach,
		meta: { guest: true },
	},
	{
		name: "login.coach",
		path: "/login/coach",
		component: LoginCoach,
		meta: { guest: false },
	},
	{
		name: "login.player",
		path: "/login/player",
		component: LoginPlayer,
		meta: { guest: false },
	},
	{
		name: "register.player",
		path: "/register/player",
		component: RegisterPlayer,
		meta: { guest: true },
	},
  {
    name: "register.complete",
    path: "/complete/:id",
    component: RegisterComplete,
		meta: { guest: true },
  },
	{
		name: "dashboard",
		path: "/dashboard",
		component: DashBoard,
		meta: { requiresAuth: true },
	},
	{
		name: "playerDashboard",
		path: "/player-dashboard",
		component: () => import("@/pages/dashboard/Player.vue"),
		meta: { requiresAuth: true },
	},
	{
		name: "create.training",
		path: "/create/:slug",
		component: IndexTrainingPage,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "create.trainingMode",
		path: "/create/mode",
		component: IndexTrainingMode,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "create.trainingCage",
		path: "/create/cage",
		component: IndexTrainingCage,
		meta: { requiresAuth: true },
		props: false,
	},
	{
		name: "create.ab",
		path: "/create/live",
		component: IndexTrainingABPage,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training",
		path: "/training/:slug",
		props: true,
		component: () => import("@/pages/training/PracticeList.vue"),
		meta: {
			requiresAuth: true,
		},
	},
	{
		name: "track.batting",
		path: "/track/batting",
		component: TrackBatting,
		meta: { requiresAuth: true },
	},
	{
		name: "track.bullpen",
		path: "/track/bullpen",
		component: TrackBullpen,
		meta: { requiresAuth: true },
	},
	{
		name: "track.trainingMode",
		path: "/track/training-mode/:mode",
		component: TrackTrainingMode,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "track.trainingCage",
		path: "/track/training-cage/:cageHeight/:lengthCage/:widthCage",
		component: TrackTrainingCage,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "track.live",
		path: "/track/live",
		component: TrackLiveAB,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "statistic",
		path: "/statistic",
		component: () => import("@/pages/statistics/Statistic.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.statsCage",
		path: "/training/stats-cage/:idPractice/:isComplete?",
		component: () => import("@/pages/training/StadisticCage.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.statsMode",
		path: "/training/stats-mode/:mode/:idPractice/:isComplete?",
		component: () => import("@/pages/training/StatisticTrainingMode.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.stats",
		path: "/training/stats/:idPractice/:type/:isComplete?",
		component: () => import("@/pages/training/PracticeStats.vue"),
		meta: { requiresAuth: true },
		props: true,

	},
	{
		name: "roster",
		path: "/roster",
		component: Roster,
		meta: { requiresAuth: true },
	},
	{
		name: "roster.editPlayer",
		path: "/roster/player/:id",
		props: true,
		component: EditPlayer,
		meta: { requiresAuth: true },
	},
	{
		name: "manage",
		path: "/manage",
		component: Manage,
		meta: { requiresAuth: true },
	},
	{
		name: "manage.team",
		path: "/manage/create",
		component: CreateTeam,
		meta: { requiresAuth: true },
	},
  {
    name: "manage.team.update",
    path: "/manage/create/:id",
    component: () => import("@/pages/manage/UpdateTeam.vue"),
    meta: { requiresAuth: true },
  },
	{
		name: "profile",
		path: "/profile",
		component: EditProfile,
		meta: { requiresAuth: true },
	},
	{
		name: "profile-player",
		path: "/profile-player",
		component: EditProfilePlayer,
		meta: { requiresAuth: true },
	},
	{
		name: "change-password",
		path: "/change-password",
		component: ChangePassword,
		meta: { requiresAuth: true },
	},
	{
		name: "training.liveAB",
		path: "/trainingb/:slug",
		component: () => import("@/pages/training/PracticeListLiveAb.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.statsLiveAB",
		path: "/training/live-ab/statistic/:id",
		component: () => import("@/pages/training/LiveABStatistic.vue"),
		meta: { requiresAuth: true },
	},
  
  /* only for redundant player options */
  {
    name: "training-player",
		path: "/training-player/:slug",
		props: true,
		component: () => import("@/pages/training/PracticeList.vue"),
		meta: {
			requiresAuth: true,
		},
  },
];

const router = createRouter({
	history: createWebHistory(),
	routes,
});

router.beforeEach((to, from, next) => {
	const { isLogged } = useAuthStore();
	if (to.matched.some((record) => record.meta.requiresAuth)) {
		if (isLogged.status) {
			next();
			return;
		}
		next("/");
	} else {
		next();
	}
});

router.beforeEach((to, from, next) => {
	const { isLogged } = useAuthStore();
	const { userData } = useUserStore();

	if (to.matched.some((record) => record.meta.guest)) {
		if (isLogged.status) {
			if (userData.type == "coach") {
				next("/dashboard");
			} else {
				next("/player-dashboard");
			}
			return;
		}
		next();
	} else {
		next();
	}
});
export default router;
