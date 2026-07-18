import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../js/store/auth";
import { useUserStore } from "../js/store/user";
import { useAccessStore } from "../js/store/access";
import { getAuthToken } from "../js/utils/authToken.js";

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
const AssessmentReports = () => import("@/pages/dashboard/AssessmentReports.vue");
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
const PracticePlanner = () => import("@/pages/practice/PracticePlanner.vue");
const Manage = () => import("@/pages/manage/HomeManage.vue");
const CreateTeam = () => import("@/pages/manage/CreateTeam.vue");
const EditProfile = () => import("@/pages/profile/EditProfile.vue");
const EditProfilePlayer = () => import("@/pages/profile/EditProfilePlayer.vue");
const Settings = () => import("@/pages/profile/Settings.vue");
const ChangePassword = () => import("@/pages/profile/ChangePassword.vue");
const EditPlayer = () => import("@/pages/roster/EditPlayer.vue");
const TrackLiveAB = () => import("@/pages/training/LiveAB.vue");
const NewStatistic = () => import("@/pages/statistics/NewStatistic.vue");
const NewStatsSessionView = () => import("@/pages/statistics/NewStatsSessionView.vue");
const PlayerDevelopmentDashboard = () => import('@/features/development/pages/PlayerDevelopmentDashboard.vue');
const TeamDevelopmentDashboard = () => import('@/features/development/pages/TeamDevelopmentDashboard.vue');
const CoachDevelopmentDashboard = () => import('@/features/development/pages/CoachDevelopmentDashboard.vue');
const AdminBenchmarksDashboard = () => import('@/features/development/pages/AdminBenchmarksDashboard.vue');
const AdminDashboard   = () => import('@/pages/admin/AdminDashboard.vue');
const AdminUsers       = () => import('@/pages/admin/AdminUsers.vue');
const AdminUserDetail  = () => import('@/pages/admin/AdminUserDetail.vue');
const AdminTeams       = () => import('@/pages/admin/AdminTeams.vue');
const AdminRoles       = () => import('@/pages/admin/AdminRoles.vue');
const AdminSecurity    = () => import('@/pages/admin/AdminSecurity.vue');
const AdminAuditLogs   = () => import('@/pages/admin/AdminAuditLogs.vue');
const AdminReports     = () => import('@/pages/admin/AdminReports.vue');
const AdminPlans       = () => import('@/pages/admin/AdminPlans.vue');

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
		name: "assessment.reports",
		path: "/assessment-reports",
		component: AssessmentReports,
		meta: { requiresAuth: true, entitlement: 'view_assessment_reports' },
	},
	{
		name: "playerDashboard",
		path: "/player-dashboard",
		component: () => import("@/pages/dashboard/Player.vue"),
		meta: { requiresAuth: true },
	},
	{
		name: "arm.care",
		path: "/arm-care",
		component: () => import("@/pages/training/ArmCare.vue"),
		meta: { requiresAuth: true, entitlement: 'arm_care' },
	},
	{
		name: 'development.index',
		path: '/development',
		component: PlayerDevelopmentDashboard,
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: 'development.team',
		path: '/development/team',
		component: TeamDevelopmentDashboard,
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: 'development.coach',
		path: '/development/coach',
		component: CoachDevelopmentDashboard,
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: 'development.player',
		path: '/development/player/:playerId?',
		component: PlayerDevelopmentDashboard,
		// A coach opening one of their players' development pages (from the dashboard
		// dev board / roster cards) must be allowed, not just a player viewing their
		// own. 'view_advanced_stats' is held by BOTH Coach Pro and Player Pro (and not
		// by Basic/Free), so it gates correctly for every audience. 'development_graphs'
		// was Player-Pro-only, which bounced coaches back to the dashboard.
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
		props: true,
	},
	{
		name: 'development.admin.benchmarks',
		path: '/development/admin/benchmarks',
		component: AdminBenchmarksDashboard,
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
		meta: { requiresAuth: true, entitlement: 'liveab_sessions' },
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
		meta: { requiresAuth: true, entitlement: 'liveab_sessions' },
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
		name: "new-statistic",
		path: "/new-statistic",
		component: NewStatistic,
		meta: { requiresAuth: true, entitlement: 'view_team_stats' },
		props: true,
	},
	{
		name: "new-statistic-session-view",
		path: "/new-statistic/session-view",
		component: NewStatsSessionView,
		meta: { requiresAuth: true, entitlement: 'view_session_report' },
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
		name: "session.report",
		path: "/session/report/:id/:type",
		component: () => import("@/pages/training/SessionReport.vue"),
		meta: { requiresAuth: true, entitlement: 'view_session_report' },
		props: true,
	},
	{
		name: "sessions.all",
		path: "/sessions",
		component: () => import("@/pages/training/AllSessions.vue"),
		meta: { requiresAuth: true },
	},
	{
		name: "roster",
		path: "/roster",
		component: Roster,
		meta: { requiresAuth: true },
	},
	{
		name: "practice.planner",
		path: "/practice-planner",
		component: PracticePlanner,
		meta: { requiresAuth: true, entitlement: 'planner_create' },
	},
	{
		name: "roster.editPlayer",
		path: "/roster/player/:id",
		props: true,
		component: EditPlayer,
		meta: { requiresAuth: true, entitlement: 'edit_player' },
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
		meta: { requiresAuth: true, entitlement: 'add_team' },
	},
  {
    name: "manage.team.update",
    path: "/manage/create/:id",
    component: () => import("@/pages/manage/UpdateTeam.vue"),
    meta: { requiresAuth: true, entitlement: 'edit_team' },
  },
	{
		name: "settings",
		path: "/settings",
		component: Settings,
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
  
  // ── Admin routes ──────────────────────────────────────────────────────────
  { name: 'admin.dashboard',  path: '/admin',                component: AdminDashboard,  meta: { requiresAuth: true } },
  { name: 'admin.users',      path: '/admin/users',          component: AdminUsers,      meta: { requiresAuth: true } },
  { name: 'admin.coaches',    path: '/admin/coaches',        component: AdminUsers,      meta: { requiresAuth: true } },
  { name: 'admin.players',    path: '/admin/players',        component: AdminUsers,      meta: { requiresAuth: true } },
  { name: 'admin.user-detail',path: '/admin/users/:id',      component: AdminUserDetail, meta: { requiresAuth: true }, props: true },
  { name: 'admin.teams',      path: '/admin/teams',          component: AdminTeams,      meta: { requiresAuth: true } },
  { name: 'admin.roles',      path: '/admin/roles',          component: AdminRoles,      meta: { requiresAuth: true } },
  { name: 'admin.security',   path: '/admin/security',       component: AdminSecurity,   meta: { requiresAuth: true } },
  { name: 'admin.auditlogs',  path: '/admin/audit-logs',     component: AdminAuditLogs,  meta: { requiresAuth: true } },
  { name: 'admin.reports',    path: '/admin/reports',        component: AdminReports,    meta: { requiresAuth: true } },
  { name: 'admin.plans',      path: '/admin/plans',          component: AdminPlans,      meta: { requiresAuth: true } },

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

const WEB_START_PRACTICE_ENABLED = false;
const START_PRACTICE_BLOCKED_PATHS = ['/create', '/track'];

export const routeEntitlement = (to) => {
	if (to.meta?.entitlement) return to.meta.entitlement;
	if (to.name !== 'track.trainingMode') return null;

	return {
		EV: 'exit_velocity_sessions',
		LT: 'long_toss_sessions',
		WB: 'weighted_ball_sessions',
	}[String(to.params?.mode || '').toUpperCase()] || null;
};

const syncAuthFromToken = () => {
	const auth = useAuthStore();
	const token = getAuthToken();
	if (token) {
		auth.setToken(token);
		auth.isLogged.status = true;
		return true;
	}
	return !!auth.isLogged.status;
};

router.beforeEach((to, from, next) => {
	const isAuthenticated = syncAuthFromToken();
	if (to.matched.some((record) => record.meta.requiresAuth)) {
		if (isAuthenticated) {
			if (!WEB_START_PRACTICE_ENABLED && START_PRACTICE_BLOCKED_PATHS.some((path) => to.path.startsWith(path))) {
				next('/dashboard');
				return;
			}
			next();
			return;
		}
		next("/");
	} else {
		next();
	}
});

router.beforeEach(async (to, from, next) => {
	if (!to.matched.some((record) => record.meta.requiresAuth)) {
		next();
		return;
	}

	const entitlement = routeEntitlement(to);
	if (!entitlement) {
		next();
		return;
	}

	const access = useAccessStore();
	try {
		await access.refresh();
	} catch (_) {
		next({ path: '/dashboard', query: { access_denied: entitlement } });
		return;
	}

	if (!access.canAccess(entitlement)) {
		next({ path: '/dashboard', query: { access_denied: entitlement } });
		return;
	}

	next();
});

router.beforeEach((to, from, next) => {
	const isAuthenticated = syncAuthFromToken();
	const { userData } = useUserStore();

	if (to.matched.some((record) => record.meta.guest)) {
		if (isAuthenticated) {
			const adminEmail = String(userData?.email || '').toLowerCase();
			if (adminEmail === 'admin@fungometrics.com') {
				next('/admin');
			} else if (userData.type == "coach") {
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

router.beforeEach((to, from, next) => {
	const { userData } = useUserStore();
	const email = String(userData?.email || '').toLowerCase();
	const isAdmin = email === 'admin@fungometrics.com';

	if (to.path.startsWith('/admin')) {
		if (!isAdmin) {
			next('/dashboard');
			return;
		}
	}

	// Keep admin inside the admin section — block access to coach/player-facing pages
	if (isAdmin && !to.path.startsWith('/admin')) {
		next('/admin');
		return;
	}

	next();
});

export default router;
