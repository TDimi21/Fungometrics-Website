import { describe, it, expect, vi } from 'vitest';
import { sessionPage, playerSessionHistory } from '@/utils/sessionPagination';
describe('player session history', () => {
  it('supports next, previous and disabled pagination controls', () => {
    expect(sessionPage({url:'https://app.example/api/player/sessions/batting?page=2',label:'Next &raquo;'})).toBe(2);
    expect(sessionPage({url:'?page=1',label:'&laquo; Previous'})).toBe(1);
    expect(sessionPage({url:null})).toBeNull();
    expect(sessionPage({url:'?page=0'})).toBeNull();
    expect(sessionPage({url:'?page=2',active:true})).toBeNull();
  });
  it('loads older sessions with mode filtering without trusting response hosts', async () => {
    const get=vi.fn().mockResolvedValueOnce({data:{data:{data:[{id:'new'}],next_page_url:'https://untrusted.example/?page=2'}}}).mockResolvedValueOnce({data:{data:{data:[{id:'old'}],next_page_url:null}}});
    expect(await playerSessionHistory(get,'LT')).toEqual([{id:'new'},{id:'old'}]);
    expect(get.mock.calls).toEqual([['player/sessions/training',{mode:'LT',page:1}],['player/sessions/training',{mode:'LT',page:2}]]);
  });
  it('uses the live AB endpoint and rejects broken pagination', async () => {
    const get=vi.fn(async()=>({data:{data:{data:[],next_page_url:'?page=1'}}}));
    await expect(playerSessionHistory(get,'L')).rejects.toThrow('pagination');
    expect(get).toHaveBeenCalledWith('player/sessions/liveab',{page:1});
  });
});
